<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserBulkReactivationTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        return ['--path' => array_values(array_filter(glob(database_path('migrations/*.php')), fn ($path) => basename($path) !== '2026_09_25_000003_add_monthly_period_to_reward_unlocks.php')), '--realpath' => true];
    }

    private function admin(): User
    {
        $role = Role::firstOrCreate(['name' => 'Admin'], ['slug' => 'admin', 'department' => 'Admin']);
        return User::factory()->create(['role_id' => $role->id]);
    }

    private function suspended(User $admin): User
    {
        return User::factory()->create(['suspended_at' => now(), 'suspended_by' => $admin->id, 'suspension_reason' => 'Suspended']);
    }

    public function test_admin_can_reactivate_multiple_selected_users_and_skip_active_users(): void
    {
        $admin = $this->admin();
        $first = $this->suspended($admin);
        $second = $this->suspended($admin);
        $unselected = $this->suspended($admin);
        $active = User::factory()->create();
        $before = $active->fresh()->getAttributes();
        $this->actingAs($admin)->from(route('admin.users.index'))->patch(route('admin.users.bulk-unsuspend'), [
            'user_ids' => [$first->id, $second->id, $active->id],
        ])->assertRedirect(route('admin.users.index'))->assertSessionHas('success', '2 users reactivated. Already active users were skipped.');
        foreach ([$first, $second] as $user) {
            $this->assertDatabaseHas('users', ['id' => $user->id, 'suspended_at' => null, 'suspended_by' => null, 'suspension_reason' => null]);
        }
        $this->assertNotNull($unselected->fresh()->suspended_at);
        $this->assertSame($before, $active->fresh()->getAttributes());
        $this->get(route('admin.users.index'))->assertOk()->assertSee(route('admin.users.bulk-unsuspend'), false);
    }

    public function test_non_admin_cannot_reactivate_users(): void
    {
        $admin = $this->admin();
        $target = $this->suspended($admin);
        $role = Role::firstOrCreate(['name' => 'Lead Miner'], ['slug' => 'lead-miner', 'department' => 'Production']);
        $user = User::factory()->create(['role_id' => $role->id]);
        $this->actingAs($user)->patch(route('admin.users.bulk-unsuspend'), ['user_ids' => [$target->id]])->assertForbidden();
        $this->assertNotNull($target->fresh()->suspended_at);
    }

    public function test_invalid_or_deleted_selection_does_not_partially_reactivate(): void
    {
        $admin = $this->admin();
        $target = $this->suspended($admin);
        $deleted = $this->suspended($admin);
        $deleted->delete();
        $this->actingAs($admin)->patch(route('admin.users.bulk-unsuspend'), ['user_ids' => [$target->id, $deleted->id]])
            ->assertSessionHasErrors('user_ids.1');
        $this->assertNotNull($target->fresh()->suspended_at);
        $this->assertSoftDeleted($deleted);
        $this->patch(route('admin.users.bulk-unsuspend'), ['user_ids' => []])->assertSessionHasErrors('user_ids');
        $this->patch(route('admin.users.bulk-unsuspend'), ['user_ids' => [$target->id, $target->id]])->assertSessionHasErrors('user_ids.0');
        $this->assertNotNull($target->fresh()->suspended_at);
    }
}
