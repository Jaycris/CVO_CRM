<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Role;
use App\Models\SalesEndorsement;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceAvailabilityTest extends TestCase
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
        $brand = Brand::create(['imprint_name' => 'Event Test Brand']);
        $role = Role::firstOrCreate(['name' => 'Admin'], ['slug' => 'admin', 'department' => 'Admin']);

        return User::factory()->create(['brand_id' => $brand->id, 'role_id' => $role->id, 'is_commission_eligible' => true]);
    }

    private function service(User $user): Service
    {
        return Service::create(['brand_id' => $user->brand_id, 'name' => 'Book Fair Registration', 'category' => 'Events', 'price' => 999])->fresh();
    }

    private function payload(Service $service): array
    {
        return [
            'author_name' => 'Event Author', 'contact_number' => '5550100', 'email' => 'event@example.com',
            'street_name' => 'Test Street', 'city_state' => 'Test City', 'zip_code' => '1000',
            'book_title' => 'Event Book', 'isbn' => '123', 'services' => $service->name,
            'amount' => 999, 'payment' => 'Full Payment',
        ];
    }

    public function test_admin_can_disable_and_reactivate_a_service(): void
    {
        $admin = $this->admin();
        $service = $this->service($admin);
        $this->assertTrue($service->is_active);
        $fields = ['brand_id' => $service->brand_id, 'name' => $service->name, 'category' => 'Events', 'price' => 999];

        $this->actingAs($admin)->put(route('admin.services.update', $service), $fields + ['is_active' => 0])->assertSessionHasNoErrors();
        $this->assertFalse($service->fresh()->is_active);
        $this->get(route('admin.services.index'))->assertOk()->assertSee('Disabled');

        $this->put(route('admin.services.update', $service), $fields + ['is_active' => 1])->assertSessionHasNoErrors();
        $this->assertTrue($service->fresh()->is_active);
    }

    public function test_closed_service_is_hidden_from_sales_choices_and_rejected_on_submission(): void
    {
        $admin = $this->admin();
        $service = $this->service($admin);
        $service->update(['is_active' => false]);

        $this->actingAs($admin)->get(route('sales.endorsements.create'))->assertOk()
            ->assertViewHas('serviceOptions', fn ($options) => ! in_array($service->name, $options, true));
        $this->get(route('services.index'))->assertOk()->assertDontSee($service->name);

        $payload = $this->payload($service);
        $payload['services'] = '  BOOK FAIR REGISTRATION  ';
        $this->post(route('sales.endorsements.store'), $payload)->assertSessionHasErrors('services');
        $this->assertDatabaseCount('sales_endorsements', 0);
    }

    public function test_disabling_keeps_existing_sales_and_reactivation_accepts_new_sales(): void
    {
        $admin = $this->admin();
        $service = $this->service($admin);
        $this->actingAs($admin)->post(route('sales.endorsements.store'), $this->payload($service))->assertSessionHasNoErrors();
        $sale = SalesEndorsement::firstOrFail();
        $this->assertEquals($service->id, $sale->service_id);

        $service->update(['is_active' => false]);
        $this->assertEquals($service->name, $sale->fresh()->service->name);
        $this->assertDatabaseCount('sales_endorsements', 1);

        $service->update(['is_active' => true]);
        $this->post(route('sales.endorsements.store'), $this->payload($service))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('sales_endorsements', 2);
    }

    public function test_users_without_service_management_access_cannot_change_availability(): void
    {
        $admin = $this->admin();
        $service = $this->service($admin);
        $user = User::factory()->create(['brand_id' => $admin->brand_id]);
        $this->actingAs($user)->put(route('admin.services.update', $service), ['is_active' => 0])->assertForbidden();
        $this->assertTrue($service->fresh()->is_active);
    }
}
