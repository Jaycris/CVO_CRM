<?php

namespace Tests\Feature;

use App\Models\Reward;
use App\Models\RewardUnlock;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RewardMediaOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        // The unrelated rewards migration uses MySQL-only ALTER SQL.
        return [
            '--path' => array_values(array_filter(glob(database_path('migrations/*.php')), fn ($path) => basename($path) !== '2026_09_25_000003_add_monthly_period_to_reward_unlocks.php')),
            '--realpath' => true,
        ];
    }

    private function admin(): User
    {
        $role = Role::firstOrCreate(['name' => 'Admin'], ['slug' => 'admin', 'department' => 'Admin']);

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function reward(): Reward
    {
        $reward = Reward::create(['title' => 'Hotel Stay', 'requirement_amount' => 15000]);
        foreach (['room.jpg', 'hotel.jpg', 'pool.jpg'] as $index => $name) {
            $reward->media()->create(['type' => 'image', 'path' => 'rewards/' . $name, 'original_name' => $name, 'sort_order' => $index]);
        }

        return $reward;
    }

    public function test_admin_can_change_main_photo_and_gallery_order(): void
    {
        $admin = $this->admin();
        $reward = $this->reward();
        $ids = $reward->media->pluck('id')->all();
        $order = [$ids[1], $ids[2], $ids[0]];

        $this->actingAs($admin)->put(route('admin.rewards.media-order', $reward), ['media_order' => $order])
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.rewards.index', ['page' => 1]));

        $this->assertSame($order, $reward->fresh()->media->pluck('id')->all());
        $this->assertSame('hotel.jpg', $reward->fresh()->media->first()->original_name);
        $this->get(route('admin.rewards.index'))->assertOk()
            ->assertSee(route('admin.rewards.edit', $reward))->assertDontSee('Save Photo Order');
    }

    public function test_partial_duplicate_and_foreign_media_orders_are_rejected_without_changes(): void
    {
        $reward = $this->reward();
        $other = $this->reward();
        $ids = $reward->media->pluck('id')->all();
        $this->actingAs($this->admin());

        foreach ([[$ids[0]], [$ids[0], $ids[0], $ids[2]], [$ids[0], $ids[1], $other->media->first()->id]] as $order) {
            $this->put(route('admin.rewards.media-order', $reward), ['media_order' => $order])->assertSessionHasErrors();
            $this->assertSame($ids, $reward->fresh()->media->pluck('id')->all());
        }
    }

    public function test_users_without_management_permission_cannot_reorder_media(): void
    {
        $reward = $this->reward();
        $ids = $reward->media->pluck('id')->all();
        $this->actingAs(User::factory()->create())->put(route('admin.rewards.media-order', $reward), ['media_order' => array_reverse($ids)])
            ->assertForbidden();
        $this->assertSame($ids, $reward->fresh()->media->pluck('id')->all());
    }

    private function uploadPayload(): array
    {
        return [
            'title' => 'Hotel Stay', 'requirement_type' => 'sales_mtd', 'requirement_amount' => 15000,
            'reward_scope' => 'individual', 'audience' => 'all_users', 'is_active' => 1,
            'media' => [UploadedFile::fake()->image('room.jpg'), UploadedFile::fake()->image('hotel.jpg')],
        ];
    }

    public function test_new_uploads_can_be_ordered_before_creation(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin())->post(route('admin.rewards.store'), $this->uploadPayload() + ['upload_order' => [1, 0]])
            ->assertSessionHasNoErrors();

        $reward = Reward::firstOrFail();
        $this->assertSame(['hotel.jpg', 'room.jpg'], $reward->media->pluck('original_name')->all());
        Storage::disk('public')->assertExists($reward->media->first()->path);
    }

    public function test_upload_order_must_contain_every_file_once(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());
        foreach ([[0], [0, 0], [0, 9]] as $order) {
            $this->post(route('admin.rewards.store'), $this->uploadPayload() + ['upload_order' => $order])->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('rewards', 0);
    }

    public function test_uploads_without_an_explicit_order_keep_upload_order(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin())->post(route('admin.rewards.store'), $this->uploadPayload())->assertSessionHasNoErrors();
        $this->assertSame(['room.jpg', 'hotel.jpg'], Reward::firstOrFail()->media->pluck('original_name')->all());
    }

    private function editPayload(): array
    {
        return [
            'title' => 'Updated Hotel Stay', 'accommodation' => 'Two nights for two guests',
            'requirements' => 'Confirmed payments only', 'requirement_type' => 'sold_mined_leads',
            'requirement_amount' => 8, 'reward_scope' => 'team', 'audience' => 'lead_generation',
            'expires_in_days' => 14, 'is_active' => 0,
        ];
    }

    public function test_edit_page_prefills_details_and_shows_full_photo_editor(): void
    {
        $reward = $this->reward();
        $this->actingAs($this->admin())->get(route('admin.rewards.edit', $reward))->assertOk()
            ->assertSee('Edit Reward')->assertSee('Hotel Stay')->assertSee('room.jpg')
            ->assertSee('Save Changes')->assertSee('Add Photos / Video')
            ->assertSee('name="media_order[]"', false);
    }

    public function test_save_changes_updates_all_details_and_order_without_resetting_claims(): void
    {
        $admin = $this->admin();
        $reward = $this->reward();
        $claim = RewardUnlock::create([
            'reward_id' => $reward->id, 'user_id' => $admin->id, 'progress_amount' => 15000,
            'unlocked_at' => now()->subDays(2), 'claim_requested_at' => now()->subDay(), 'claimed_at' => now(),
        ]);
        $before = $claim->fresh()->getAttributes();
        $order = $reward->media->reverse()->map(fn ($item) => 'existing-' . $item->id)->values()->all();
        $this->actingAs($admin)->put(route('admin.rewards.update', $reward), $this->editPayload() + ['media_order' => $order])
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.rewards.index'));
        $this->assertDatabaseHas('rewards', ['id' => $reward->id] + $this->editPayload());
        $this->assertSame($order, $reward->fresh()->media->map(fn ($item) => 'existing-' . $item->id)->all());
        $this->assertSame($before, $claim->fresh()->getAttributes());
    }

    public function test_editor_can_add_remove_and_promote_a_new_upload_to_main(): void
    {
        Storage::fake('public');
        $reward = $this->reward();
        foreach ($reward->media as $item) Storage::disk('public')->put($item->path, 'original');
        $ids = $reward->media->pluck('id')->all();
        $this->actingAs($this->admin())->put(route('admin.rewards.update', $reward), $this->editPayload() + [
            'media' => [UploadedFile::fake()->image('new-main.jpg')],
            'remove_media' => [$ids[1]],
            'media_order' => ['upload-0', 'existing-' . $ids[2], 'existing-' . $ids[0]],
        ])->assertSessionHasNoErrors();
        $this->assertSame(['new-main.jpg', 'pool.jpg', 'room.jpg'], $reward->fresh()->media->pluck('original_name')->all());
        Storage::disk('public')->assertMissing('rewards/hotel.jpg');
        Storage::disk('public')->assertExists('rewards/room.jpg');
        Storage::disk('public')->assertExists($reward->fresh()->media->first()->path);
    }

    public function test_invalid_editor_order_or_foreign_removal_leaves_reward_unchanged(): void
    {
        $reward = $this->reward();
        $other = $this->reward();
        $ids = $reward->media->pluck('id')->all();
        $this->actingAs($this->admin());
        foreach ([
            ['media_order' => ['existing-' . $ids[0]]],
            ['media_order' => ['existing-' . $ids[0], 'existing-' . $ids[0], 'existing-' . $ids[2]]],
            ['remove_media' => [$other->media->first()->id]],
            ['title' => ''],
        ] as $invalid) {
            $this->put(route('admin.rewards.update', $reward), array_replace($this->editPayload(), $invalid))->assertSessionHasErrors();
            $this->assertSame('Hotel Stay', $reward->fresh()->title);
            $this->assertSame($ids, $reward->fresh()->media->pluck('id')->all());
        }
    }

    public function test_editor_rejects_more_than_eight_total_media(): void
    {
        Storage::fake('public');
        $reward = $this->reward();
        $this->actingAs($this->admin())->put(route('admin.rewards.update', $reward), $this->editPayload() + [
            'media' => array_map(fn ($i) => UploadedFile::fake()->image("extra-{$i}.jpg"), range(1, 6)),
        ])->assertSessionHasErrors('media_order');
        $this->assertSame('Hotel Stay', $reward->fresh()->title);
        $this->assertCount(3, $reward->fresh()->media);
    }

    public function test_edit_and_update_are_restricted_to_reward_managers(): void
    {
        $reward = $this->reward();
        $this->actingAs(User::factory()->create())->get(route('admin.rewards.edit', $reward))->assertForbidden();
        $this->put(route('admin.rewards.update', $reward), $this->editPayload())->assertForbidden();
        $this->assertSame('Hotel Stay', $reward->fresh()->title);
    }
}
