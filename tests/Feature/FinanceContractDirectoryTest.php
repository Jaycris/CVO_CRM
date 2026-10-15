<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Role;
use App\Models\SalesEndorsement;
use App\Models\SalesPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceContractDirectoryTest extends TestCase
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

    private function record(string $paymentStatus): SalesEndorsement
    {
        $brand = Brand::create(['imprint_name' => 'Contract Test Brand']);
        $role = Role::firstOrCreate(['name' => 'Admin'], ['slug' => 'admin', 'department' => 'Admin']);
        $admin = User::factory()->create(['brand_id' => $brand->id, 'role_id' => $role->id]);
        $this->actingAs($admin);

        $endorsement = SalesEndorsement::create([
            'brand_id' => $brand->id, 'agent_id' => $admin->id, 'endorsement_code' => 'SE2026255',
            'author_name' => 'Test Author', 'contact_number' => '5550100',
            'street_name' => 'Test Street', 'city_state' => 'Test City', 'zip_code' => '1000',
            'book_title' => 'Test Book', 'isbn' => '123', 'services' => 'Test Service',
            'amount' => 1000, 'amount_to_be_paid' => 1000, 'payment' => 'Full Payment',
        ]);
        SalesPayment::create([
            'brand_id' => $brand->id, 'sales_endorsement_id' => $endorsement->id,
            'amount' => 1000, 'payment_method' => 'Card', 'sold_date' => '2026-10-14', 'status' => $paymentStatus,
        ]);

        return $endorsement;
    }

    public function test_successfully_paid_unsent_contract_can_be_found_by_se_id(): void
    {
        $endorsement = $this->record('Payment Success');

        $this->get(route('finance.contracts.index', ['search' => 'SE2026255']))->assertOk()
            ->assertViewHas('endorsements', fn ($records) => $records->count() === 1 && $records->first()->id === $endorsement->id)
            ->assertSee('SE2026255')->assertSee('Unsent');
    }

    public function test_se_id_search_does_not_bypass_successful_payment_requirement(): void
    {
        $this->record('Processing');

        $this->get(route('finance.contracts.index', ['search' => 'SE2026255']))->assertOk()
            ->assertViewHas('endorsements', fn ($records) => $records->isEmpty());
    }

    public function test_se_id_search_respects_contract_status_filter(): void
    {
        $this->record('Payment Success');

        $this->get(route('finance.contracts.index', ['status' => 'sent', 'search' => 'SE2026255']))->assertOk()
            ->assertViewHas('endorsements', fn ($records) => $records->isEmpty());
    }
}
