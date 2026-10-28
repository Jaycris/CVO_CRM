<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Brand;
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

    public function test_admin_deduction_reduces_only_agent_mtd_with_audit_and_keeps_financial_records_unchanged(): void
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
        $this->assertEquals(25, $credit['service_commission_percent']);
        foreach (['service_mtd', 'markup_mtd', 'service_commission', 'markup_commission', 'usd_total', 'php_total', 'hold_amount', 'net_commission', 'service_commission_percent'] as $key) {
            $this->assertEquals($before['agentCredits']->get($this->agent->id)[$key], $credit[$key], $key);
        }
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

    public function test_agent_dashboard_and_mtd_api_show_adjustment_but_commission_slip_is_unchanged(): void
    {
        $this->sale();
        $query = ['hris_employee_id' => 'EMP-MTD-1', 'month' => '2026-10'];
        $slip = $this->withToken('mtd-deduction-test-token')->getJson(route('api.hris.commission-slip', $query))->assertOk()->json();
        $this->deduct();
        $this->get(route('reports.sales-performance.index', ['month' => '2026-10']))->assertOk()
            ->assertSee('MTD Deductions')->assertSee('Deduction -$500.00');
        $this->withToken('mtd-deduction-test-token')->getJson(route('api.hris.sales-performance-mtd', ['month' => '2026-10']))->assertOk()
            ->assertJsonPath('agents.0.mtd', 500)->assertJsonPath('agents.0.gross_mtd', 1000)->assertJsonPath('agents.0.mtd_deduction', 500);
        $after = $this->withToken('mtd-deduction-test-token')->getJson(route('api.hris.commission-slip', $query))->assertOk()->json();
        $this->assertSame($slip, $after);
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
}
