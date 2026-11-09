<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Brand;
use App\Models\CommissionAdjustment;
use App\Models\CommissionDisputeOffset;
use App\Models\CommissionRelease;
use App\Models\Role;
use App\Models\SalesActivity;
use App\Models\SalesEndorsement;
use App\Models\SalesMtdDeduction;
use App\Models\SalesPayment;
use App\Models\SalesTarget;
use App\Models\Service;
use App\Models\User;
use App\Support\SalesActivitySync;
use App\Support\SalesMtdCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class RefundCommissionTest extends TestCase
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
        $this->travelTo(Carbon::parse('2026-11-10 12:00:00'));
        Notification::fake();
        Http::preventStrayRequests();
        config(['services.hris.commission_release_token' => 'refund-release-token']);
        AppSetting::set('hris_base_url', 'https://phrems.test');
        AppSetting::set('hris_api_token', 'refund-api-token');
        AppSetting::set('commission_exchange_rate', 60);
        AppSetting::set('card_payment_hold_percent', 15);
        $brand = Brand::create(['imprint_name' => 'Refund Test Brand', 'is_sales_brand' => true]);
        $role = Role::firstOrCreate(['name' => 'Admin'], ['slug' => 'admin', 'department' => 'Admin']);
        $this->admin = User::factory()->create(['brand_id' => $brand->id, 'role_id' => $role->id, 'department' => 'Admin']);
        $this->agent = User::factory()->create(['brand_id' => $brand->id, 'department' => 'Sales', 'is_commission_eligible' => true,
            'is_commission_threshold_exempt' => true, 'markup_commission_percent' => 50, 'hris_employee_id' => 'EMP-REFUND']);
        $this->actingAs($this->admin);
    }

    private function payment(string $date = '2026-10-28', float $amount = 2000, float $price = 1000): SalesPayment
    {
        $service = Service::create(['brand_id' => $this->agent->brand_id, 'name' => 'Refund Service '.Str::random(8), 'category' => 'Marketing', 'price' => $price]);
        $endorsement = SalesEndorsement::create(['brand_id' => $this->agent->brand_id, 'agent_id' => $this->agent->id,
            'author_name' => 'Pat Mann', 'book_title' => 'Refund Book '.$amount, 'contact_number' => '5550100',
            'street_name' => 'Street', 'city_state' => 'City', 'zip_code' => '1000', 'isbn' => '123',
            'service_id' => $service->id, 'services' => $service->name, 'amount' => $amount, 'amount_to_be_paid' => $amount, 'payment' => 'Full Payment']);
        $payment = SalesPayment::create(['brand_id' => $this->agent->brand_id, 'sales_endorsement_id' => $endorsement->id,
            'amount' => $amount, 'payment_method' => 'Card', 'sold_date' => $date, 'status' => 'Payment Success']);
        SalesActivitySync::sync($payment);
        return $payment;
    }

    private function respond(array $statuses): void
    {
        Http::fake(function (Request $request) use ($statuses) {
            $data = [];
            foreach ($request['items'] as $item) {
                [$status, $usd] = $statuses[$item['crm_payment_id']] ?? ['unknown', 0];
                $result = ['key' => $item['key'], 'status' => $status];
                if ($status === 'released') $result += ['reference' => 'slip:41/line:'.$item['key'], 'released_at' => '2026-10-30T08:00:00Z',
                    'breakdown' => ['usd_total' => $usd, 'service_commission' => $usd, 'markup_commission' => 0,
                        'php_total' => $usd * 60, 'hold_amount' => round($usd * 60 * .15, 2), 'net_commission' => round($usd * 60 * .85, 2),
                        'exchange_rate' => 60, 'card_payment_hold_percent' => 15]];
                $data[] = $result;
            }
            return Http::response(['complete' => true, 'hris_employee_id' => $request['hris_employee_id'], 'month' => $request['month'], 'release_basis' => 'sent', 'data' => $data]);
        });
    }

    private function updateStatus(SalesPayment $payment, string $status)
    {
        return $this->put(route('finance.payments.update', $payment->id), ['amount' => $payment->amount,
            'payment_method' => $payment->payment_method, 'sold_date' => $payment->sold_date->toDateString(), 'status' => $status]);
    }

    private function slip(string $month, array $extra = [], string $employee = 'EMP-REFUND')
    {
        return $this->withToken('refund-api-token')->getJson(route('api.hris.commission-slip', $extra + ['month' => $month, 'hris_employee_id' => $employee]));
    }

    private function freeze(SalesPayment $payment): void
    {
        $activity = SalesActivity::where('sales_payment_id', $payment->id)->firstOrFail();
        $row = SalesMtdCalculator::statementRows(collect([$activity]))->first();
        $snapshot = collect($row)->except(['activity', 'agent', 'commission_profile'])->all();
        $snapshot['original_sold_date'] = $activity->sold_date->toDateString();
        $activity->update(['commission_snapshot' => [$this->agent->id => $snapshot]]);
    }

    public function test_unreleased_refund_removes_mtd_and_reverses_all_commission_and_hold_once(): void
    {
        $payment = $this->payment();
        $this->respond([$payment->id => ['unreleased', 0]]);
        $this->updateStatus($payment, 'Refund')->assertSessionHasNoErrors();
        $this->updateStatus($payment, 'Refund')->assertSessionHasNoErrors();
        $this->assertDatabaseCount('commission_adjustments', 1);
        $entry = CommissionAdjustment::firstOrFail();
        $this->assertSame('refund', $entry->kind);
        $this->assertSame('unreleased', $entry->settlement_basis);
        $this->assertEquals(-650, $entry->usd_amount);
        $this->assertSame('2026-10-28', $entry->effective_date->toDateString());
        $slip = $this->slip('2026-10')->assertOk()->json();
        foreach (['mtd', 'gross_mtd', 'service_commission', 'markup_commission', 'usd_total', 'php_total', 'card_hold_amount', 'net_commission'] as $field) {
            $this->assertEquals(0, $slip['summary'][$field], $field);
        }
        $this->assertEquals(0, $slip['summary']['monthly_mtd']);
        $this->assertEquals(0, $this->slip('2026-11')->assertOk()->json('summary.usd_total'));
        $this->assertEquals(0, SalesMtdCalculator::summary($this->admin, Carbon::parse('2026-10-01'), $this->agent->brand_id)['global']['mtd']);
        $this->get(route('reports.agent-statements.index', ['month' => '2026-10', 'agent_id' => $this->agent->id]))->assertOk()
            ->assertSee('Refunded sale history')->assertSee('Refund commission reversal')->assertDontSee('Dispute commission restored')
            ->assertViewHas('totals', fn ($totals) => $totals['sales_credit'] == 0 && $totals['usd_total'] == 0 && $totals['net_commission'] == 0);
        $this->assertDatabaseCount('sales_activities', 1);
    }

    public function test_released_refund_uses_saved_paid_amount_in_current_month_and_keeps_zero_original_mtd(): void
    {
        $payment = $this->payment();
        $this->respond([$payment->id => ['released', 600]]);
        $this->updateStatus($payment, 'Refund')->assertSessionHasNoErrors();
        $this->assertEquals(-600, CommissionAdjustment::first()->usd_amount);
        $this->assertSame('2026-11-10', CommissionAdjustment::first()->effective_date->toDateString());
        $this->assertEquals(0, $this->slip('2026-10')->assertOk()->json('summary.mtd'));
        $this->assertEquals(600, $this->slip('2026-10')->assertOk()->json('summary.usd_total'));
        $this->assertEquals(-600, $this->slip('2026-11')->assertOk()->json('summary.usd_total'));
        $this->assertEquals(-600, $this->slip('2026-12')->assertOk()->json('summary.usd_total'));
    }

    public function test_switching_refund_and_dispute_does_not_charge_twice_and_restoration_restores_mtd(): void
    {
        $payment = $this->payment();
        $this->respond([$payment->id => ['unreleased', 0]]);
        $this->updateStatus($payment, 'Dispute')->assertSessionHasNoErrors();
        $this->updateStatus($payment, 'Refund')->assertSessionHasNoErrors();
        $this->assertDatabaseCount('commission_adjustments', 1);
        $this->assertEquals(0, $this->slip('2026-10')->assertOk()->json('summary.mtd'));
        $this->updateStatus($payment, 'Payment Success')->assertSessionHasNoErrors();
        $this->updateStatus($payment, 'Payment Success')->assertSessionHasNoErrors();
        $this->assertDatabaseCount('commission_adjustments', 2);
        $this->assertEquals(2000, $this->slip('2026-10')->assertOk()->json('summary.mtd'));
        $this->assertEquals(650, $this->slip('2026-10')->assertOk()->json('summary.usd_total'));
    }

    public function test_fetch_repairs_the_archived_pat_mann_refund_and_voids_its_funding_offset(): void
    {
        SalesTarget::create(['brand_id' => $this->agent->brand_id, 'user_id' => $this->agent->id, 'target_type' => 'agent', 'target_month' => '2026-10-01', 'amount' => 1000]);
        $source = $this->payment('2026-10-16', 1500, 999);
        $funding = $this->payment('2026-10-28', 2280, 1680);
        $other = $this->payment('2026-10-28', 1999, 1999);
        $this->respond([$source->id => ['released', 500.25], $funding->id => ['unreleased', 0], $other->id => ['unreleased', 0]]);
        $this->updateStatus($source, 'Dispute')->assertSessionHasNoErrors();
        $this->assertEquals(500.25, CommissionDisputeOffset::sum('usd_amount'));
        // Reproduce the former Refund path, including the frozen funding row surviving in Trash.
        foreach ([$funding, $other] as $payment) {
            $payment->fresh()->update(['status' => 'Refund']);
            SalesActivitySync::sync($payment->fresh());
            $payment->fresh()->delete();
        }
        $this->slip('2026-10')->assertStatus(422);
        $this->slip('2026-11')->assertStatus(422);
        $this->get(route('reports.agent-statements.index', ['month' => '2026-10', 'agent_id' => $this->agent->id]))
            ->assertOk()->assertSee('refunds awaiting commission reconciliation');
        $this->post(route('reports.commission-releases.sync'), ['month' => '2026-10', 'agent_id' => $this->agent->id])->assertSessionHasNoErrors();
        $this->post(route('reports.commission-releases.sync'), ['month' => '2026-10', 'agent_id' => $this->agent->id])->assertSessionHasNoErrors();
        $this->assertNotNull(CommissionDisputeOffset::first()->voided_at);
        $this->assertSame('The funding commission was refunded before release.', CommissionDisputeOffset::first()->void_reason);
        $refund = CommissionAdjustment::where('sales_payment_id', $funding->id)->firstOrFail();
        $this->assertSame('refund', $refund->kind);
        $this->assertEquals(-720, $refund->usd_amount);
        $this->assertDatabaseCount('commission_adjustments', 2);
        $this->assertSoftDeleted($funding);
        $this->assertEquals(1500, $this->slip('2026-10')->assertOk()->json('summary.mtd'));
        $this->assertEquals(500.25, $this->slip('2026-10')->assertOk()->json('summary.usd_total'));
        $this->assertEquals(-500.25, $this->slip('2026-11')->assertOk()->json('summary.usd_total'));
        $run = $this->slip('2026-10', ['from' => '2026-10-25', 'to' => '2026-10-31'])->assertOk()->json('summary');
        $this->assertEquals(0, $run['mtd']);
        $this->assertEquals(0, $run['net_commission']);
    }

    public function test_older_released_refund_requires_admin_to_supply_the_actual_date(): void
    {
        $payment = $this->payment();
        $this->freeze($payment);
        $payment->fresh()->update(['status' => 'Refund']);
        $payment->fresh()->delete();
        $this->respond([$payment->id => ['released', 650]]);
        $this->post(route('reports.commission-releases.sync'), ['month' => '2026-10', 'agent_id' => $this->agent->id])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('commission_adjustments', 0);
        $this->slip('2026-11')->assertStatus(422);
        $this->get(route('reports.commission-releases.index', ['month' => '2026-10', 'agent_id' => $this->agent->id]))
            ->assertOk()->assertSee('Reconcile existing refund');
        $url = route('reports.commission-releases.refunds.reconcile', $payment->id);
        $params = ['refunded_on' => '2026-11-09', 'reason' => 'Confirmed original refund date with Finance'];
        $this->actingAs($this->agent)->post($url, $params)->assertForbidden();
        $this->actingAs($this->admin)->post($url, array_replace($params, ['refunded_on' => '2026-12-01']))->assertSessionHasErrors('refunded_on');
        $this->post($url, $params)->assertSessionHasNoErrors();
        $this->post($url, $params)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('commission_adjustments', 1);
        $entry = CommissionAdjustment::firstOrFail();
        $this->assertSame('2026-11-09', $entry->effective_date->toDateString());
        $this->assertEquals($this->admin->id, $entry->original_commission['refund_reconciliation']['created_by']);
        $this->assertEquals(-650, $this->slip('2026-11')->assertOk()->json('summary.usd_total'));
    }

    public function test_unknown_release_blocks_refund_and_changed_payment_amounts_cannot_bypass_validation(): void
    {
        $payment = $this->payment();
        $this->respond([$payment->id => ['unknown', 0]]);
        $this->updateStatus($payment, 'Refund')->assertSessionHasErrors('status');
        $this->assertSame('Payment Success', $payment->fresh()->status);
        $this->assertDatabaseCount('commission_adjustments', 0);
        $this->assertNull(SalesActivity::first()->commission_snapshot);
        $this->put(route('finance.payments.update', $payment->id), ['status' => 'Refund', 'amount' => 1000,
            'payment_method' => 'Card', 'sold_date' => '2026-10-28'])->assertSessionHasErrors('status');
        $this->assertEquals(2000, $payment->fresh()->amount);
    }

    public function test_refund_with_existing_mtd_deduction_cancels_only_its_original_payable_commission(): void
    {
        $payment = $this->payment();
        SalesMtdDeduction::create(['request_key' => (string) Str::uuid(), 'agent_id' => $this->agent->id, 'brand_id' => $this->agent->brand_id,
            'effective_month' => '2026-10-01', 'amount' => 500, 'reason' => 'Company adjustment', 'created_by' => $this->admin->id]);
        $this->respond([$payment->id => ['unreleased', 0]]);
        $this->updateStatus($payment, 'Refund')->assertSessionHasNoErrors();
        $this->assertEquals(-487.50, CommissionAdjustment::first()->usd_amount);
        $slip = $this->slip('2026-10')->assertOk()->json('summary');
        $this->assertEquals(-500, $slip['mtd']);
        $this->assertEquals(0, $slip['usd_total']);
        $this->assertEquals(0, $slip['net_commission']);
    }

    public function test_removing_refunded_credit_recalculates_remaining_unfrozen_sales_using_the_monthly_tier(): void
    {
        SalesTarget::create(['brand_id' => $this->agent->brand_id, 'user_id' => $this->agent->id, 'target_type' => 'agent', 'target_month' => '2026-10-01', 'amount' => 2500]);
        $refund = $this->payment('2026-10-14');
        $remaining = $this->payment('2026-10-28');
        $this->respond([$refund->id => ['unreleased', 0], $remaining->id => ['unreleased', 0]]);
        $this->assertEquals(1500, $this->slip('2026-10')->assertOk()->json('summary.usd_total'));
        $this->updateStatus($refund, 'Refund')->assertSessionHasNoErrors();
        $data = $this->slip('2026-10')->assertOk()->json();
        $this->assertEquals(2000, $data['summary']['mtd']);
        $this->assertEquals(80, $data['summary']['monthly_mtd_percent']);
        $this->assertEquals(700, $data['summary']['usd_total']);
        $other = collect($data['transactions'])->first(fn ($row) => $row['crm_payment_id'] == $remaining->id);
        $this->assertEquals(20, $other['service_commission_percent']);
        $this->assertEquals(-750, CommissionAdjustment::first()->usd_amount);
    }

    public function test_shared_refund_removes_each_agents_mtd_and_cancels_each_share_only_once(): void
    {
        $payment = $this->payment();
        $frankie = User::factory()->create(['brand_id' => $this->agent->brand_id, 'department' => 'Sales', 'is_commission_eligible' => true,
            'is_commission_threshold_exempt' => true, 'markup_commission_percent' => 50, 'hris_employee_id' => 'EMP-FRANKIE-REFUND']);
        $payment->endorsement->update(['has_frankie' => true, 'frankie_agent_id' => $frankie->id, 'frankie_commission_percent' => 50]);
        $payment->unsetRelation('endorsement');
        SalesActivitySync::sync($payment);
        $this->respond([$payment->id => ['unreleased', 0]]);
        $this->updateStatus($payment, 'Refund')->assertSessionHasNoErrors();
        $this->updateStatus($payment, 'Refund')->assertSessionHasNoErrors();
        $this->assertDatabaseCount('commission_adjustments', 2);
        foreach (['EMP-REFUND', 'EMP-FRANKIE-REFUND'] as $employee) {
            $data = $this->slip('2026-10', [], $employee)->assertOk()->json('summary');
            $this->assertEquals(0, $data['mtd']);
            $this->assertEquals(0, $data['usd_total']);
            $this->assertEquals(0, $data['net_commission']);
        }
    }

    public function test_missing_original_activity_cannot_hide_an_unreversed_released_refund_from_payroll(): void
    {
        $payment = $this->payment();
        CommissionRelease::create(['sales_payment_id' => $payment->id, 'agent_id' => $this->agent->id, 'brand_id' => $this->agent->brand_id,
            'status' => 'released', 'source' => 'phrems', 'released_at' => '2026-10-30', 'commission_breakdown' => ['usd_total' => 650], 'reason' => 'Original sent slip']);
        $payment->update(['status' => 'Refund']);
        SalesActivitySync::sync($payment);
        $this->assertDatabaseCount('sales_activities', 0);
        $this->slip('2026-11')->assertStatus(422);
        $this->get(route('reports.commission-releases.index', ['month' => '2026-10', 'agent_id' => $this->agent->id]))
            ->assertOk()->assertSee('Original commission history is missing')->assertSee('#'.$payment->id);
        $this->assertDatabaseCount('commission_adjustments', 0);
    }
}
