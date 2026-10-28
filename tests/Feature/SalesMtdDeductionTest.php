<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Brand;
use App\Models\CommissionAdjustment;
use App\Models\CommissionProfile;
use App\Models\Reward;
use App\Models\Role;
use App\Models\SalesActivity;
use App\Models\SalesEndorsement;
use App\Models\SalesMtdDeduction;
use App\Models\SalesPayment;
use App\Models\SalesTarget;
use App\Models\Service;
use App\Models\Team;
use App\Models\User;
use App\Support\RewardProgress;
use App\Support\CommissionDisputes;
use App\Support\SalesMtdCalculator;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SalesMtdDeductionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $agent;

    protected function migrateFreshUsing(): array
    {
        // This unrelated rewards migration uses MySQL-only ALTER SQL.
        return [
            '--path' => array_values(array_filter(glob(database_path('migrations/*.php')), fn ($path) => basename($path) !== '2026_09_25_000003_add_monthly_period_to_reward_unlocks.php')),
            '--realpath' => true,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-10-29 12:00:00'));
        AppSetting::set('commission_exchange_rate', 56);
        AppSetting::set('card_payment_hold_percent', 15);
        AppSetting::set('hris_api_token', 'mtd-deduction-test-token');
        $brand = Brand::create(['imprint_name' => 'MTD Test Brand', 'is_sales_brand' => true]);
        $role = Role::firstOrCreate(['name' => 'Admin'], ['slug' => 'admin', 'department' => 'Admin']);
        $this->admin = User::factory()->create(['brand_id' => $brand->id, 'role_id' => $role->id, 'department' => 'Admin']);
        $this->agent = User::factory()->create([
            'brand_id' => $brand->id, 'department' => 'Sales', 'is_commission_eligible' => true,
            'is_commission_threshold_exempt' => true, 'hris_employee_id' => 'EMP-MTD-1', 'work_type' => 'remote',
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'request_key' => (string) Str::uuid(), 'agent_id' => $this->agent->id,
            'month' => '2026-10', 'amount' => 500, 'reason' => 'Company adjustment approved by Admin',
        ], $overrides);
    }

    private function deduct(array $overrides = []): SalesMtdDeduction
    {
        $this->actingAs($this->admin)->post(route('admin.sales-mtd-deductions.store'), $this->payload($overrides))->assertSessionHasNoErrors();

        return SalesMtdDeduction::latest('id')->firstOrFail();
    }

    private function sale(?User $agent = null, string $date = '2026-10-10'): SalesActivity
    {
        $agent ??= $this->agent;
        $service = Service::firstOrCreate(['brand_id' => $agent->brand_id, 'name' => 'MTD Test Service'], ['category' => 'Marketing', 'price' => 500]);
        $endorsement = SalesEndorsement::create([
            'brand_id' => $agent->brand_id, 'agent_id' => $agent->id, 'author_name' => 'MTD Test Author',
            'contact_number' => '5550100', 'street_name' => 'Test Street', 'city_state' => 'Test City', 'zip_code' => '1000',
            'book_title' => 'MTD Test Book', 'isbn' => '123', 'services' => $service->name, 'service_id' => $service->id,
            'amount' => 1000, 'amount_to_be_paid' => 1000, 'payment' => 'Full Payment',
        ]);
        $payment = SalesPayment::create([
            'brand_id' => $agent->brand_id, 'sales_endorsement_id' => $endorsement->id,
            'amount' => 1000, 'payment_method' => 'Card', 'sold_date' => $date, 'status' => 'Payment Success',
        ]);

        return SalesActivity::create([
            'brand_id' => $agent->brand_id, 'agent_id' => $agent->id, 'sales_endorsement_id' => $endorsement->id,
            'sales_payment_id' => $payment->id, 'service_id' => $service->id,
            'activity_type' => 'payment_success', 'author_name' => 'MTD Test Author', 'book_title' => 'MTD Test Book',
            'service_name' => $service->name, 'amount' => 1000, 'agent_credit_amount' => 1000,
            'sold_date' => $date, 'payment_method' => 'Card', 'payment_status' => 'Payment Success',
        ]);
    }

    private function summary(string $month = '2026-10', ?User $viewer = null, ?int $brandId = null): array
    {
        return SalesMtdCalculator::summary($viewer, Carbon::parse($month . '-01'), $brandId);
    }

    public function test_admin_deduction_recalculates_agent_commission_and_keeps_original_records_unchanged(): void
    {
        $sale = $this->sale();
        SalesTarget::create([
            'brand_id' => $this->agent->brand_id, 'user_id' => $this->agent->id,
            'target_type' => 'agent', 'target_month' => '2026-10-01', 'amount' => 1000,
        ]);
        $original = [$sale->fresh()->getAttributes(), $sale->payment->getAttributes(), $sale->endorsement->getAttributes()];
        $before = $this->summary();
        $entry = $this->deduct(['created_by' => 999, 'brand_id' => 999]);
        $after = $this->summary();
        $credit = $after['agentCredits']->get($this->agent->id);

        $this->assertEquals(500, $credit['mtd']);
        $this->assertEquals(1000, $credit['gross_mtd']);
        $this->assertEquals(500, $credit['mtd_deduction']);
        foreach ([
            'service_mtd' => 250, 'markup_mtd' => 250, 'service_commission_percent' => 15,
            'service_commission' => 37.5, 'markup_commission' => 125, 'usd_total' => 162.5,
            'php_total' => 9100, 'hold_amount' => 1365, 'net_commission' => 7735,
        ] as $key => $value) {
            $this->assertEquals($value, $credit[$key], $key);
        }
        $this->assertEquals(25, $before['agentCredits']->get($this->agent->id)['service_commission_percent']);
        $this->assertSame($before['global'], $after['global']);
        $this->assertSame($before['remote'], $after['remote']);
        $this->assertEquals($this->admin->id, $entry->created_by);
        $this->assertEquals($this->agent->brand_id, $entry->brand_id);
        $this->assertSame('2026-10-01', $entry->effective_month->toDateString());
        $this->assertSame($original, [$sale->fresh()->getAttributes(), $sale->payment->fresh()->getAttributes(), $sale->endorsement->fresh()->getAttributes()]);
        $this->assertDatabaseCount('commission_adjustments', 0);
    }

    public function test_deductions_stack_only_in_selected_month_without_carrying_forward(): void
    {
        $this->sale();
        $this->deduct(['amount' => 700]);
        $this->deduct(['amount' => 500]);
        $this->deduct(['amount' => 10, 'month' => '2026-11']);

        $this->assertEquals(-200, $this->summary()['agentCredits']->get($this->agent->id)['mtd']);
        $this->assertEquals(-10, $this->summary('2026-11')['agentCredits']->get($this->agent->id)['mtd']);
        $this->assertFalse($this->summary('2026-12')['agentCredits']->has($this->agent->id));
    }

    public function test_void_restores_agent_mtd_and_keeps_original_audit_record(): void
    {
        $this->sale();
        $entry = $this->deduct();
        $original = $entry->only(['amount', 'reason', 'created_by', 'created_at']);
        $this->post(route('admin.sales-mtd-deductions.void', $entry), ['void_reason' => 'Entered against wrong employee'])->assertSessionHasNoErrors();
        $entry->refresh();
        $this->assertEquals(1000, $this->summary()['agentCredits']->get($this->agent->id)['mtd']);
        $this->assertEquals(325, $this->summary()['agentCredits']->get($this->agent->id)['usd_total']);
        $this->assertNotNull($entry->voided_at);
        $this->assertEquals($this->admin->id, $entry->voided_by);
        $this->assertEquals($original, $entry->only(['amount', 'reason', 'created_by', 'created_at']));
        $this->post(route('admin.sales-mtd-deductions.void', $entry), ['void_reason' => 'Retry'])->assertSessionHasNoErrors();
        $this->assertSame('Entered against wrong employee', $entry->fresh()->void_reason);
        $this->assertDatabaseCount('sales_mtd_deductions', 1);
    }

    public function test_repeated_form_submission_does_not_deduct_twice(): void
    {
        $payload = $this->payload();
        $this->actingAs($this->admin);
        $this->post(route('admin.sales-mtd-deductions.store'), $payload)->assertSessionHasNoErrors();
        $this->post(route('admin.sales-mtd-deductions.store'), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('sales_mtd_deductions', 1);
        $this->assertEquals(-500, $this->summary()['agentCredits']->get($this->agent->id)['mtd']);
    }

    public function test_non_admin_cannot_view_create_or_void_deductions(): void
    {
        $entry = $this->deduct();
        $this->actingAs($this->agent);
        $this->get(route('admin.sales-mtd-deductions.index'))->assertForbidden();
        $this->post(route('admin.sales-mtd-deductions.store'), $this->payload())->assertForbidden();
        $this->post(route('admin.sales-mtd-deductions.void', $entry), ['void_reason' => 'Not authorized'])->assertForbidden();
        $this->assertNull($entry->fresh()->voided_at);
        $this->assertDatabaseCount('sales_mtd_deductions', 1);
    }

    public function test_invalid_amounts_agents_months_and_reasons_are_rejected(): void
    {
        $this->actingAs($this->admin);
        foreach ([['amount' => 0], ['amount' => -1], ['amount' => '1.001'], ['amount' => 'text'], ['month' => 'bad'], ['reason' => ''], ['agent_id' => $this->admin->id]] as $invalid) {
            $this->post(route('admin.sales-mtd-deductions.store'), $this->payload($invalid))->assertSessionHasErrors(array_keys($invalid));
        }
        $this->assertDatabaseCount('sales_mtd_deductions', 0);
    }

    public function test_summary_respects_agent_and_brand_scope(): void
    {
        $otherBrand = Brand::create(['imprint_name' => 'Other MTD Brand', 'is_sales_brand' => true]);
        $other = User::factory()->create(['brand_id' => $otherBrand->id, 'department' => 'Sales']);
        $this->deduct();
        $this->deduct(['agent_id' => $other->id, 'amount' => 200]);
        $summary = $this->summary('2026-10', $this->agent);
        $this->assertTrue($summary['agentCredits']->has($this->agent->id));
        $this->assertFalse($summary['agentCredits']->has($other->id));
        $brandSummary = $this->summary('2026-10', null, $otherBrand->id);
        $this->assertFalse($brandSummary['agentCredits']->has($this->agent->id));
        $this->assertEquals(-200, $brandSummary['agentCredits']->get($other->id)['mtd']);
    }

    public function test_dashboard_statements_and_hris_use_the_same_adjusted_commission(): void
    {
        $this->sale();
        $query = ['hris_employee_id' => 'EMP-MTD-1', 'month' => '2026-10'];
        $slip = $this->withToken('mtd-deduction-test-token')->getJson(route('api.hris.commission-slip', $query))->assertOk()->json();
        $this->deduct();
        $dashboard = $this->get(route('reports.sales-performance.index', ['month' => '2026-10']))->assertOk()
            ->assertSee('MTD Deductions')->assertSee('Deduction -$500.00');
        $this->assertMatchesRegularExpression('/<button type="submit"[^>]*>\s*Search\s*<\/button>\s*<a[^>]*>MTD Deductions<\/a>/s', $dashboard->getContent());
        $this->withToken('mtd-deduction-test-token')->getJson(route('api.hris.sales-performance-mtd', ['month' => '2026-10']))->assertOk()
            ->assertJsonPath('agents.0.mtd', 500)->assertJsonPath('agents.0.gross_mtd', 1000)->assertJsonPath('agents.0.mtd_deduction', 500);
        $after = $this->withToken('mtd-deduction-test-token')->getJson(route('api.hris.commission-slip', $query))->assertOk()->json();
        $this->assertEquals(500, $after['summary']['mtd']);
        $this->assertEquals(162.5, $after['summary']['usd_total']);
        $this->assertEquals(7735, $after['summary']['net_commission']);
        $this->assertLessThan($slip['summary']['net_commission'], $after['summary']['net_commission']);
        $this->assertSame($slip['transactions'][0]['sale_amount'], $after['transactions'][0]['sale_amount']);
        $this->assertEquals(500, $after['transactions'][0]['mtd_deduction']);
        $this->assertEquals(500, $after['transactions'][0]['credited_mtd']);
        $this->get(route('reports.agent-statements.index', ['month' => '2026-10', 'agent_id' => $this->agent->id]))->assertOk()
            ->assertViewHas('totals', fn ($totals) => $totals['net_commission'] === 7735.0 && $totals['sales_credit'] === 500.0);
        $this->get(route('admin.sales-mtd-deductions.index', ['month' => '2026-10']))->assertOk()->assertSee('Company adjustment approved by Admin');
    }

    public function test_reward_progress_stays_based_on_original_sales(): void
    {
        // Mirror the missing period column from the skipped MySQL-only migration for this test.
        Schema::table('reward_unlocks', fn (Blueprint $table) => $table->date('period_month')->nullable());
        $this->sale();
        $this->deduct();
        $reward = Reward::create([
            'title' => 'Original sales reward', 'reward_scope' => 'individual', 'audience' => 'all_users',
            'requirement_type' => 'sales_mtd', 'requirement_amount' => 800, 'is_active' => true,
        ]);
        $summary = $this->summary();
        $result = RewardProgress::visibleFor($this->agent, $summary)->firstWhere('id', $reward->id);
        $this->assertEquals(1000, $result->progress_amount);
        $this->assertTrue($result->is_unlocked);
        $this->actingAs($this->agent)->get(route('rewards.index'))->assertOk()->assertViewHas('individualMtd', 1000.0);
    }

    public function test_team_mtd_and_other_agents_stay_unchanged(): void
    {
        $team = Team::create(['brand_id' => $this->agent->brand_id, 'department' => 'Sales', 'name' => 'Sales Test Team', 'show_on_sales_dashboard' => true]);
        $this->agent->update(['team_id' => $team->id]);
        $other = User::factory()->create(['brand_id' => $this->agent->brand_id, 'department' => 'Sales']);
        $this->sale();
        $this->sale($other);
        $this->deduct();
        $summary = $this->summary();
        $this->assertEquals(1000, $summary['agentCredits']->get($other->id)['mtd']);
        $this->assertEquals(2000, $summary['global']['mtd']);
        $this->get(route('reports.sales-performance.index', ['month' => '2026-10']))->assertOk()
            ->assertViewHas('teamRows', fn ($rows) => $rows->firstWhere('id', $team->id)['mtd'] === 1000.0);
    }

    private function slipFor(string $from, string $to): array
    {
        return $this->withToken('mtd-deduction-test-token')->getJson(route('api.hris.commission-slip', [
            'hris_employee_id' => 'EMP-MTD-1', 'month' => substr($from, 0, 7), 'from' => $from, 'to' => $to,
        ]))->assertOk()->json();
    }

    public function test_partial_month_slips_allocate_deduction_once_and_reconcile_to_month_totals(): void
    {
        $this->sale(null, '2026-10-01');
        $this->sale(null, '2026-10-16');
        $this->deduct();
        $early = $this->slipFor('2026-10-01', '2026-10-10');
        $late = $this->slipFor('2026-10-11', '2026-10-31');
        $full = $this->slipFor('2026-10-01', '2026-10-31');

        $this->assertEquals(250, $early['summary']['mtd_deduction']);
        $this->assertEquals(250, $late['summary']['mtd_deduction']);
        $this->assertEquals(1500, $full['summary']['mtd']);
        foreach (['mtd', 'mtd_deduction', 'service_commission', 'markup_commission', 'usd_total', 'php_total', 'card_hold_amount', 'net_commission'] as $key) {
            $this->assertEqualsWithDelta($full['summary'][$key], $early['summary'][$key] + $late['summary'][$key], 0.001, $key);
        }
        $this->assertSame(['2026-10-01'], array_column($early['transactions'], 'sold_date'));
        $this->assertSame(['2026-10-16'], array_column($late['transactions'], 'sold_date'));
    }

    public function test_card_hold_recalculation_stays_limited_to_card_payments(): void
    {
        $this->sale(null, '2026-10-01');
        $wire = $this->sale(null, '2026-10-16');
        $wire->update(['payment_method' => 'Wire Payment']);
        $wire->payment->update(['payment_method' => 'Wire Payment']);
        $this->deduct();
        $slip = $this->slipFor('2026-10-01', '2026-10-31');

        $this->assertEquals(618.75, $slip['summary']['usd_total']);
        $this->assertEquals(2047.5, $slip['summary']['card_hold_amount']);
        $this->assertEquals(32602.5, $slip['summary']['net_commission']);
        $this->assertEquals(0, collect($slip['transactions'])->firstWhere('payment_method', 'Wire Payment')['card_hold_amount']);
    }

    public function test_threshold_and_over_deduction_do_not_create_negative_earned_commission(): void
    {
        $this->agent->update(['is_commission_threshold_exempt' => false, 'commission_threshold_amount' => 500]);
        $this->sale();
        $this->deduct();
        $credit = $this->summary()['agentCredits']->get($this->agent->id);
        $this->assertEquals(500, $credit['mtd']);
        $this->assertEquals(500, $credit['threshold_applied_amount']);
        $this->assertEquals(0, $credit['net_commission']);

        $this->deduct(['amount' => 600]);
        $credit = $this->summary()['agentCredits']->get($this->agent->id);
        $this->assertEquals(-100, $credit['mtd']);
        $this->assertEquals(1100, $credit['mtd_deduction']);
        foreach (['service_mtd', 'markup_mtd', 'usd_total', 'php_total', 'hold_amount', 'net_commission'] as $key) {
            $this->assertEquals(0, $credit[$key], $key);
        }
    }

    public function test_cent_allocations_are_exact_across_separate_ranges(): void
    {
        foreach (['2026-10-01', '2026-10-11', '2026-10-21'] as $date) $this->sale(null, $date);
        $this->deduct(['amount' => '100.01']);
        $ranges = [
            $this->slipFor('2026-10-01', '2026-10-10'), $this->slipFor('2026-10-11', '2026-10-20'),
            $this->slipFor('2026-10-21', '2026-10-31'),
        ];
        $full = $this->slipFor('2026-10-01', '2026-10-31');
        $this->assertEquals(100.01, $full['summary']['mtd_deduction']);
        $this->assertEquals(2899.99, $full['summary']['mtd']);
        $this->assertEqualsWithDelta(100.01, array_sum(array_column(array_column($ranges, 'summary'), 'mtd_deduction')), 0.001);
        $this->assertEqualsWithDelta($full['summary']['net_commission'], array_sum(array_column(array_column($ranges, 'summary'), 'net_commission')), 0.001);
    }

    public function test_shared_sale_deduction_recalculates_only_selected_agent_share(): void
    {
        $other = User::factory()->create([
            'brand_id' => $this->agent->brand_id, 'department' => 'Sales', 'is_commission_eligible' => true,
            'is_commission_threshold_exempt' => true, 'markup_commission_percent' => 50,
        ]);
        $sale = $this->sale();
        $sale->update(['agent_credit_amount' => 500, 'frankie_agent_id' => $other->id, 'frankie_credit_amount' => 500]);
        $before = $this->summary()['agentCredits']->get($other->id);
        $this->deduct(['amount' => 100]);
        $summary = $this->summary();
        $credit = $summary['agentCredits']->get($this->agent->id);
        $this->assertEquals(400, $credit['mtd']);
        $this->assertEquals(130, $credit['usd_total']);
        $this->assertEquals(6188, $credit['net_commission']);
        $this->assertEquals($before['mtd'], $summary['agentCredits']->get($other->id)['mtd']);
        $this->assertEquals($before['net_commission'], $summary['agentCredits']->get($other->id)['net_commission']);
    }

    public function test_dispute_reverses_adjusted_commission_not_the_gross_commission(): void
    {
        $sale = $this->sale(null, '2026-09-10');
        $entry = $this->deduct(['month' => '2026-09']);
        $payment = $sale->payment;
        CommissionDisputes::transition($payment, 'Dispute');

        $this->assertEquals(-162.5, CommissionAdjustment::firstOrFail()->usd_amount);
        $snapshot = $sale->fresh()->commission_snapshot[$this->agent->id];
        $this->assertEquals(325, $snapshot['usd_total']);
        $this->assertEquals(162.5, $snapshot['paid_usd_total']);
        $this->post(route('admin.sales-mtd-deductions.void', $entry), ['void_reason' => 'Correction'])->assertSessionHasNoErrors();
        $this->assertEquals(325, $this->summary('2026-09')['agentCredits']->get($this->agent->id)['usd_total']);
        $this->assertEquals(-162.5, CommissionAdjustment::firstOrFail()->usd_amount);
    }

    public function test_commission_debt_carry_forward_uses_reduced_earned_commission(): void
    {
        CommissionAdjustment::create([
            'brand_id' => $this->agent->brand_id, 'agent_id' => $this->agent->id,
            'kind' => 'dispute', 'effective_date' => '2026-09-15', 'usd_amount' => -300, 'original_commission' => [],
        ]);
        $this->sale();
        $this->deduct();
        $credit = $this->summary('2026-11')['agentCredits']->get($this->agent->id);
        $this->assertEquals(-161.88, $credit['usd_total']);
        $this->assertEquals(-9065.28, $credit['net_commission']);
    }

    public function test_last_day_sale_matches_between_dashboard_statements_and_slip(): void
    {
        $this->sale(null, '2026-10-31');
        $this->deduct();
        $credit = $this->summary()['agentCredits']->get($this->agent->id);
        $slip = $this->slipFor('2026-10-01', '2026-10-31');
        $this->assertEquals(500, $credit['mtd']);
        $this->assertEquals($credit['net_commission'], $slip['summary']['net_commission']);
        $this->get(route('reports.agent-statements.index', ['month' => '2026-10', 'agent_id' => $this->agent->id]))->assertOk()
            ->assertViewHas('totals', fn ($totals) => $totals['net_commission'] === $credit['net_commission']);
    }

    public function test_custom_tier_percentage_can_stay_the_same_while_commission_amounts_decrease(): void
    {
        $profile = CommissionProfile::create(['name' => 'Ten percent custom scheme']);
        $profile->rules()->create(['minimum_mtd_percent' => 0, 'commission_percent' => 10]);
        $this->agent->update(['commission_profile_id' => $profile->id]);
        AppSetting::set('commission_exchange_rate', 60);
        $this->sale();
        $before = $this->summary()['agentCredits']->get($this->agent->id);
        $this->deduct(['amount' => 100]);
        $credit = $this->summary()['agentCredits']->get($this->agent->id);
        $this->assertEquals(10, $before['service_commission_percent']);
        $this->assertEquals(10, $credit['service_commission_percent']);
        $this->assertEquals(45, $credit['service_commission']);
        $this->assertEquals(225, $credit['markup_commission']);
        $this->assertEquals(270, $credit['usd_total']);
        $this->assertEquals(16200, $credit['php_total']);
        $this->assertEquals(2430, $credit['hold_amount']);
        $this->assertEquals(13770, $credit['net_commission']);
    }
}
