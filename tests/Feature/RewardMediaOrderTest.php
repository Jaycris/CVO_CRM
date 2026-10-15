<?php

namespace Tests\Feature;

use App\Models\Reward;
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
        $this->get(route('admin.rewards.index'))->assertOk()->assertSee('Save Photo Order');
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
}
