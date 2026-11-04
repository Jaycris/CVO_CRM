<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Lead;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SalesActivity;
use App\Models\SalesEndorsement;
use App\Models\Service;
use App\Models\User;
use App\Support\SalesMtdCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SalesEndorsementPricingTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;
    private Service $service;

    protected function migrateFreshUsing(): array
    {
        return ['--path' => array_values(array_filter(glob(database_path('migrations/*.php')), fn ($path) => basename($path) !== '2026_09_25_000003_add_monthly_period_to_reward_unlocks.php')), '--realpath' => true];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $brand = Brand::create(['imprint_name' => 'Pricing Test Brand']);
        $role = Role::create(['name' => 'Pricing Agent', 'slug' => 'pricing-agent', 'department' => 'Sales']);
        $role->permissionRecords()->sync(Permission::whereIn('key', ['submit_sales_endorsement', 'view_own_sales_endorsements'])->pluck('id'));
        $this->agent = User::factory()->create([
            'brand_id' => $brand->id, 'role_id' => $role->id, 'department' => 'Sales',
            'is_commission_eligible' => true, 'is_commission_threshold_exempt' => true,
        ]);
        $this->service = Service::create(['brand_id' => $brand->id, 'name' => 'Pricing Service', 'category' => 'Marketing', 'price' => 2999, 'is_active' => true]);
        $this->actingAs($this->agent);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'service_id' => $this->service->id, 'services' => 'Forged service name', 'markup_amount' => '0',
            'author_name' => 'Pricing Author', 'contact_number' => '5550100', 'email' => 'author@example.test',
            'street_name' => 'Street', 'city_state' => 'City', 'zip_code' => '1000',
            'book_title' => 'Pricing Book', 'isbn' => '123', 'payment' => 'Installment Payment',
            'amount' => 1, 'service_price_snapshot' => 1,
        ], $overrides);
    }

    private function submit(array $overrides = []): SalesEndorsement
    {
        $this->post(route('sales.endorsements.store'), $this->payload($overrides))
            ->assertSessionHasNoErrors()->assertRedirect(route('sales.endorsements.index'));
        return SalesEndorsement::latest('id')->firstOrFail();
    }

    public function test_zero_partial_and_maximum_markup_use_server_price_and_automatic_totals(): void
    {
        foreach (['0' => '2999.00', '2998.99' => '5997.99', '2999.00' => '5998.00'] as $markup => $total) {
            $record = $this->submit(['markup_amount' => (string) $markup]);
            $this->assertSame('2999.00', $record->service_price_snapshot);
            $this->assertSame($this->service->name, $record->services);
            $this->assertSame($total, $record->amount);
            $this->assertSame($total, $record->amount_to_be_paid);
            $this->assertEquals($markup, $record->markup_amount);
        }
        $this->get(route('sales.endorsements.index'))->assertOk()->assertSee('Markup: $2,999.00')->assertSee('Service: $2,999.00');
    }

    public function test_excess_negative_missing_and_fractional_cent_markup_are_rejected(): void
    {
        foreach (['2999.01', '-1', '1.001', 'text', null] as $markup) {
            $this->post(route('sales.endorsements.store'), $this->payload(['markup_amount' => $markup]))->assertSessionHasErrors('markup_amount');
        }
        $this->assertDatabaseCount('sales_endorsements', 0);
    }

    public function test_custom_unpriced_disabled_deleted_and_other_brand_services_cannot_bypass_the_limit(): void
    {
        foreach ([['price' => null], ['is_active' => false]] as $change) {
            $this->service->update($change);
            $this->post(route('sales.endorsements.store'), $this->payload())->assertSessionHasErrors('service_id');
            $this->service->update(['price' => 2999, 'is_active' => true]);
        }
        foreach ([null, 999999] as $id) {
            $this->post(route('sales.endorsements.store'), $this->payload(['service_id' => $id]))->assertSessionHasErrors('service_id');
        }
        $other = Brand::create(['imprint_name' => 'Other Pricing Brand']);
        $this->service->update(['brand_id' => $other->id]);
        $this->post(route('sales.endorsements.store'), $this->payload())->assertSessionHasErrors('service_id');
        $this->service->delete();
        $this->post(route('sales.endorsements.store'), $this->payload())->assertSessionHasErrors('service_id');
        $this->assertDatabaseCount('sales_endorsements', 0);
    }

    public function test_current_service_price_is_checked_again_when_form_is_submitted(): void
    {
        $this->service->update(['price' => 100]);
        $this->post(route('sales.endorsements.store'), $this->payload(['markup_amount' => 2999]))->assertSessionHasErrors('markup_amount');
        $record = $this->submit(['markup_amount' => '99.99']);
        $this->assertSame('199.99', $record->amount);
        $this->assertSame('100.00', $record->service_price_snapshot);
    }

    public function test_existing_endorsements_are_not_repriced_or_backfilled(): void
    {
        $legacy = SalesEndorsement::create(array_replace($this->payload(), [
            'brand_id' => $this->agent->brand_id, 'agent_id' => $this->agent->id,
            'amount' => 20000, 'amount_to_be_paid' => 20000, 'services' => $this->service->name,
            'service_price_snapshot' => null, 'markup_amount' => null,
        ]));
        $before = $legacy->fresh()->getAttributes();
        $this->submit(['markup_amount' => 2999]);
        $this->get(route('sales.endorsements.index'))->assertOk()->assertSee('$20,000.00');
        $this->assertSame($before, $legacy->fresh()->getAttributes());
    }

    private function activity(SalesEndorsement $record, string $date, float $amount): SalesActivity
    {
        return SalesActivity::create([
            'brand_id' => $record->brand_id, 'agent_id' => $record->agent_id, 'sales_endorsement_id' => $record->id,
            'service_id' => $record->service_id, 'service_name' => $record->services,
            'author_name' => $record->author_name, 'book_title' => $record->book_title,
            'activity_type' => 'payment_success', 'amount' => $amount, 'agent_credit_amount' => $amount,
            'payment_method' => 'Card', 'payment_status' => 'Payment Success', 'sold_date' => $date,
        ]);
    }

    public function test_installments_use_saved_price_after_catalog_changes_and_do_not_share_other_contract_bases(): void
    {
        $this->service->update(['price' => 1000]);
        $first = $this->submit(['markup_amount' => 500]);
        $this->activity($first, '2026-10-01', 500);
        $nextPayment = $this->activity($first, '2026-11-01', 1000);
        $this->service->update(['price' => 9000]);
        $rows = SalesMtdCalculator::statementRows(collect([$nextPayment->fresh()]));
        $this->assertEquals(500, $rows->first()['service_amount']);
        $this->assertEquals(500, $rows->first()['markup_amount']);
        $this->assertSame('1500.00', $first->fresh()->amount);

        $second = $this->submit(['markup_amount' => 0]);
        $secondPayment = $this->activity($second, '2026-11-01', 9000);
        $rows = SalesMtdCalculator::statementRows(collect([$nextPayment->fresh(), $secondPayment->fresh()]));
        $this->assertEquals(9500, $rows->sum('service_amount'));
        $this->assertEquals(500, $rows->sum('markup_amount'));
    }

    public function test_form_options_include_pricing_and_support_assigned_leads_from_another_brand(): void
    {
        $other = Brand::create(['imprint_name' => 'Assigned Pricing Brand']);
        $service = Service::create(['brand_id' => $other->id, 'name' => 'Other service', 'category' => 'Marketing', 'price' => 123]);
        $lead = Lead::create(['brand_id' => $other->id, 'assigned_to' => $this->agent->id, 'created_by' => $this->agent->id,
            'author_name' => 'Assigned Author', 'book_title' => 'Assigned Book', 'phone_numbers' => ['5550100']]);
        $this->get(route('sales.endorsements.create'))->assertOk()
            ->assertViewHas('serviceOptions', fn ($options) => collect($options)->contains(fn ($option) => $option['id'] === $service->id && $option['price'] === '123.00'))
            ->assertSee('Maximum markup: $')->assertSee('name="markup_amount"', false);
        $record = $this->submit(['lead_id' => $lead->id, 'service_id' => $service->id, 'markup_amount' => 123]);
        $this->assertSame('246.00', $record->amount);
        $this->assertEquals($other->id, $record->brand_id);
    }
}
