<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Brand;
use App\Models\CommissionAdjustment;
use App\Models\CommissionRelease;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SalesActivity;
use App\Models\SalesEndorsement;
use App\Models\SalesPayment;
use App\Models\Service;
use App\Models\User;
use App\Support\CommissionDisputes;
use App\Support\SalesActivitySync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CommissionReleaseTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;
    private User $agent;

    protected function migrateFreshUsing(): array
    {
        return ['--path' => array_values(array_filter(glob(database_path('migrations/*.php')), fn ($path) => basename($path) !== '2026_09_25_000003_add_monthly_period_to_reward_unlocks.php')), '--realpath' => true];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-06 12:00:00'));
        AppSetting::set('commission_exchange_rate', 56);
        AppSetting::set('card_payment_hold_percent', 15);
        AppSetting::set('hris_api_token', 'release-test-token');
        $brand = Brand::create(['imprint_name' => 'Release Test Brand', 'is_sales_brand' => true]);
        $role = Role::firstOrCreate(['name' => 'Admin'], ['slug' => 'admin', 'department' => 'Admin']);
        $this->admin = User::factory()->create(['role_id' => $role->id, 'brand_id' => $brand->id, 'department' => 'Admin']);
        $this->agent = User::factory()->create(['brand_id' => $brand->id, 'department' => 'Sales', 'is_commission_eligible' => true,
            'is_commission_threshold_exempt' => true, 'markup_commission_percent' => 50, 'hris_employee_id' => 'EMP-RELEASE']);
        $this->actingAs($this->admin);
        $this->mock(\App\Support\PhremsCommissionReleases::class, fn ($mock) => $mock->shouldReceive('syncForActivity')->andReturnNull());
    }

    private function sale(string $date = '2026-10-10', ?User $agent = null): SalesPayment
    {
        $agent ??= $this->agent;
        $service = Service::firstOrCreate(['brand_id' => $agent->brand_id, 'name' => 'Release Service'], ['category' => 'Marketing', 'price' => 1000]);
        $endorsement = SalesEndorsement::create([
            'brand_id' => $agent->brand_id, 'agent_id' => $agent->id, 'author_name' => 'Release Author '.$date,
            'contact_number' => '5550100', 'street_name' => 'Street', 'city_state' => 'City', 'zip_code' => '1000',
            'book_title' => 'Release Book '.$date, 'isbn' => '123', 'service_id' => $service->id, 'services' => $service->name,
            'amount' => 2000, 'amount_to_be_paid' => 2000, 'payment' => 'Full Payment',
        ]);
        $payment = SalesPayment::create(['brand_id' => $agent->brand_id, 'sales_endorsement_id' => $endorsement->id,
            'amount' => 2000, 'payment_method' => 'Card', 'sold_date' => $date, 'status' => 'Payment Success']);
        SalesActivitySync::sync($payment);
        return $payment;
    }

    private function confirm(SalesPayment $payment, string $status, ?User $agent = null)
    {
        return $this->post(route('reports.commission-releases.store'), [
            'sales_payment_id' => $payment->id, 'agent_id' => ($agent ?? $this->agent)->id, 'status' => $status,
            'released_at' => $status === 'released' ? now()->toDateString() : null, 'reason' => 'Payroll reference 001',
        ]);
    }

    private function transition(SalesPayment $payment, string $status): void
    {
        DB::transaction(function () use ($payment, $status) {
            $payment = SalesPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            CommissionDisputes::transition($payment, $status);
            $payment->update(['status' => $status]);
            SalesActivitySync::sync($payment);
        });
    }

    private function slip(string $month, array $extra = []): array
    {
        return $this->withToken('release-test-token')->getJson(route('api.hris.commission-slip', $extra + [
            'month' => $month, 'hris_employee_id' => 'EMP-RELEASE',
        ]))->assertOk()->json();
    }

    public function test_unknown_release_blocks_a_new_dispute_without_mutating_payment_or_snapshots(): void
    {
        $payment = $this->sale();
        $this->get(route('reports.commission-releases.index', ['month' => '2026-10']))->assertOk()->assertSee('Not confirmed');
        $this->put(route('finance.payments.update', $payment), ['amount' => 2000, 'payment_method' => 'Card', 'sold_date' => '2026-10-10', 'status' => 'Dispute'])
            ->assertSessionHasErrors('status');
        $this->assertSame('Payment Success', $payment->fresh()->status);
        $this->assertNull(SalesActivity::first()->commission_snapshot);
        $this->assertDatabaseCount('commission_adjustments', 0);
    }

    public function test_unreleased_dispute_cancels_original_commission_and_hold_without_current_debt_or_reducing_sales_mtd(): void
    {
        $payment = $this->sale();
        $this->confirm($payment, 'unreleased')->assertSessionHasNoErrors();
        $this->transition($payment, 'Dispute');
        $this->transition($payment, 'Dispute');
        $entry = CommissionAdjustment::firstOrFail();
        $this->assertSame('unreleased', $entry->settlement_basis);
        $this->assertSame('2026-10-10', $entry->effective_date->toDateString());
        $october = $this->slip('2026-10');
        $this->assertEquals(2000, $october['summary']['mtd']);
        foreach (['service_commission', 'markup_commission', 'usd_total', 'php_total', 'card_hold_amount', 'net_commission'] as $field) {
            $this->assertEquals(0, $october['summary'][$field], $field);
        }
        $this->assertEquals(0, $this->slip('2026-11')['summary']['net_commission']);
        $this->assertEquals(0, $this->slip('2026-12')['summary']['net_commission']);
        AppSetting::set('commission_exchange_rate', 61);
        $this->assertEquals(0, $this->slip('2026-10')['summary']['net_commission']);
        $this->get(route('reports.agent-statements.index', ['month' => '2026-10', 'agent_id' => $this->agent->id]))->assertOk()
            ->assertViewHas('totals', fn ($totals) => $totals['net_commission'] == 0 && $totals['sales_credit'] == 2000);
        $this->assertDatabaseCount('commission_adjustments', 1);
    }

    public function test_released_dispute_deducts_saved_usd_in_current_month_and_carries_negative_balance(): void
    {
        $payment = $this->sale();
        $this->confirm($payment, 'released')->assertSessionHasNoErrors();
        $release = CommissionRelease::firstOrFail();
        $this->assertEquals(650, $release->commission_breakdown['usd_total']);
        // A subsequent settings change must not change the amount already confirmed released.
        $this->agent->update(['is_commission_threshold_exempt' => false, 'commission_threshold_amount' => 2000]);
        $this->assertEquals(650, $release->fresh()->commission_breakdown['usd_total']);
        $this->transition($payment, 'Dispute');
        $entry = CommissionAdjustment::firstOrFail();
        $this->assertEquals(-650, $entry->usd_amount);
        $this->assertSame('2026-11-06', $entry->effective_date->toDateString());
        $this->assertSame('released', $entry->settlement_basis);
        $this->assertEquals(-650, $this->slip('2026-11')['summary']['usd_total']);
        $this->assertEquals(-650, $this->slip('2026-12')['summary']['usd_total']);
    }

    public function test_reinstatement_of_unreleased_dispute_restores_only_the_original_period_once(): void
    {
        $payment = $this->sale();
        $this->confirm($payment, 'unreleased')->assertSessionHasNoErrors();
        $this->transition($payment, 'Dispute');
        $this->travelTo(Carbon::parse('2026-12-10'));
        $this->transition($payment, 'Payment Success');
        $this->transition($payment, 'Payment Success');
        $this->assertEquals(650, $this->slip('2026-10')['summary']['usd_total']);
        $this->assertEquals(0, $this->slip('2026-11')['summary']['usd_total']);
        $this->assertEquals(0, $this->slip('2026-12')['summary']['usd_total']);
        $this->assertDatabaseCount('commission_adjustments', 2);
    }

    public function test_status_confirmation_is_admin_only_and_agents_and_finance_do_not_see_controls(): void
    {
        $payment = $this->sale();
        foreach (['Finance Officer', 'Release Agent'] as $name) {
            $role = Role::firstOrCreate(['name' => $name], ['slug' => str_replace(' ', '-', strtolower($name)), 'department' => $name === 'Finance Officer' ? 'Finance' : 'Sales']);
            $role->permissionRecords()->syncWithoutDetaching(Permission::where('key', 'view_all_agent_statements')->pluck('id'));
            $user = User::factory()->create(['role_id' => $role->id, 'department' => $name === 'Finance Officer' ? 'Finance' : 'Sales', 'brand_id' => $this->agent->brand_id]);
            $this->actingAs($user)->get(route('reports.commission-releases.index'))->assertForbidden();
            $this->confirm($payment, 'released')->assertForbidden();
            $this->get(route('reports.agent-statements.index', ['month' => '2026-10']))->assertOk()->assertDontSee('Commission Releases');
        }
        $this->assertDatabaseCount('commission_releases', 0);
    }

    public function test_confirmation_is_audited_idempotent_and_locked_during_a_dispute(): void
    {
        $payment = $this->sale();
        $this->confirm($payment, 'unreleased')->assertSessionHasNoErrors();
        $this->confirm($payment, 'unreleased')->assertSessionHasNoErrors();
        $this->assertDatabaseCount('commission_releases', 1);
        $this->assertEquals($this->admin->id, CommissionRelease::first()->created_by);
        $this->confirm($payment, 'released')->assertSessionHasNoErrors();
        $this->assertDatabaseCount('commission_releases', 2);
        $this->put(route('finance.payments.update', $payment), ['amount' => 1500, 'payment_method' => 'Card', 'sold_date' => '2026-10-10', 'status' => 'Payment Success'])
            ->assertSessionHasErrors('status');
        $this->assertEquals(2000, $payment->fresh()->amount);
        $this->transition($payment, 'Dispute');
        $this->confirm($payment, 'unreleased')->assertSessionHasErrors('status');
        $this->assertDatabaseCount('commission_releases', 2);
        $this->get(route('reports.commission-releases.index', ['month' => '2026-10']))->assertOk()->assertSee('Confirmation history (2)');
    }

    public function test_shared_sales_require_both_confirmations_and_apply_each_agents_actual_release_status(): void
    {
        $payment = $this->sale();
        $frankie = User::factory()->create(['brand_id' => $this->agent->brand_id, 'department' => 'Sales', 'is_commission_eligible' => true,
            'is_commission_threshold_exempt' => true, 'markup_commission_percent' => 50, 'hris_employee_id' => 'EMP-FRANKIE-RELEASE']);
        $payment->endorsement->update(['has_frankie' => true, 'frankie_agent_id' => $frankie->id, 'frankie_commission_percent' => 50]);
        $payment->unsetRelation('endorsement');
        SalesActivitySync::sync($payment);
        $this->confirm($payment, 'released')->assertSessionHasNoErrors();
        $this->put(route('finance.payments.update', $payment), ['amount' => 2000, 'payment_method' => 'Card', 'sold_date' => '2026-10-10', 'status' => 'Dispute'])
            ->assertSessionHasErrors('status');
        $this->assertDatabaseCount('commission_adjustments', 0);
        $this->confirm($payment, 'unreleased', $frankie)->assertSessionHasNoErrors();
        $this->transition($payment, 'Dispute');
        $this->assertEquals(-325, $this->slip('2026-11')['summary']['usd_total']);
        $this->assertEquals(325, $this->slip('2026-10')['summary']['usd_total']);
        $this->assertEquals(0, $this->slip('2026-10', ['hris_employee_id' => 'EMP-FRANKIE-RELEASE'])['summary']['net_commission']);
        $this->assertEquals(0, $this->slip('2026-11', ['hris_employee_id' => 'EMP-FRANKIE-RELEASE'])['summary']['net_commission']);
    }

    public function test_unreleased_cancellation_does_not_clear_an_unrelated_released_commission_debt(): void
    {
        $previous = $this->sale('2026-09-10');
        $this->travelTo(Carbon::parse('2026-10-20 12:00:00'));
        $this->confirm($previous, 'released')->assertSessionHasNoErrors();
        $this->transition($previous, 'Dispute');
        $pending = $this->sale();
        $this->confirm($pending, 'unreleased')->assertSessionHasNoErrors();
        $this->travelTo(Carbon::parse('2026-11-06 12:00:00'));
        $this->transition($pending, 'Dispute');
        $this->assertEquals(-650, $this->slip('2026-11')['summary']['usd_total']);
    }

    public function test_confirmations_validate_share_membership_and_release_date_without_creating_audit_rows(): void
    {
        $payment = $this->sale();
        foreach ([
            ['status' => 'invalid'], ['reason' => ''], ['status' => 'released', 'released_at' => null],
            ['status' => 'released', 'released_at' => '2026-12-01'], ['status' => 'released', 'released_at' => '2026-10-01'],
        ] as $invalid) {
            $this->post(route('reports.commission-releases.store'), $invalid + [
                'sales_payment_id' => $payment->id, 'agent_id' => $this->agent->id,
                'status' => 'unreleased', 'reason' => 'Payroll reference',
            ])->assertSessionHasErrors();
        }
        $this->confirm($payment, 'unreleased', $this->admin)->assertForbidden();
        $this->assertDatabaseCount('commission_releases', 0);
    }

    public function test_existing_legacy_dispute_deduction_is_not_moved_by_missing_release_tracking(): void
    {
        $payment = $this->sale();
        $this->confirm($payment, 'released')->assertSessionHasNoErrors();
        $this->transition($payment, 'Dispute');
        $entry = CommissionAdjustment::firstOrFail();
        $entry->update(['settlement_basis' => null]);
        CommissionRelease::query()->delete();
        $before = $entry->fresh()->getAttributes();
        $this->transition($payment, 'Dispute');
        $this->assertSame($before, $entry->fresh()->getAttributes());
        $this->assertEquals(650, $this->slip('2026-10')['summary']['usd_total']);
        $this->assertEquals(-650, $this->slip('2026-11')['summary']['usd_total']);
        $this->travelTo(Carbon::parse('2026-12-10'));
        $this->transition($payment, 'Payment Success');
        $this->assertEquals(0, $this->slip('2026-12')['summary']['usd_total']);
    }

    public function test_unreleased_dispute_cancels_the_commission_after_an_existing_mtd_deduction(): void
    {
        $payment = $this->sale();
        $this->confirm($payment, 'unreleased')->assertSessionHasNoErrors();
        \App\Models\SalesMtdDeduction::create([
            'request_key' => (string) \Illuminate\Support\Str::uuid(), 'agent_id' => $this->agent->id, 'brand_id' => $this->agent->brand_id,
            'effective_month' => '2026-10-01', 'amount' => 500, 'reason' => 'Approved MTD adjustment', 'created_by' => $this->admin->id,
        ]);
        $this->assertEquals(487.5, $this->slip('2026-10')['summary']['usd_total']);
        $this->transition($payment, 'Dispute');
        $this->assertEquals(-487.5, CommissionAdjustment::firstOrFail()->usd_amount);
        $this->assertEquals(0, $this->slip('2026-10')['summary']['net_commission']);
        $this->assertEquals(0, $this->slip('2026-11')['summary']['net_commission']);
    }
}
