<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Brand;
use App\Models\CommissionAdjustment;
use App\Models\SalesActivity;
use App\Models\SalesTarget;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CommissionSlipDateRangeTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;
    private Service $service;

    protected function migrateFreshUsing(): array
    {
        // This unrelated rewards migration contains MySQL-only ALTER SQL.
        return [
            '--path' => array_values(array_filter(glob(database_path('migrations/*.php')), fn ($path) => basename($path) !== '2026_09_25_000003_add_monthly_period_to_reward_unlocks.php')),
            '--realpath' => true,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        AppSetting::set('hris_api_token', 'commission-slip-test-token');
        AppSetting::set('commission_exchange_rate', 56);
        AppSetting::set('card_payment_hold_percent', 15);
        $brand = Brand::create(['imprint_name' => 'Slip Test Brand']);
        $this->agent = User::factory()->create([
            'brand_id' => $brand->id, 'hris_employee_id' => 'EMP-4415',
            'is_commission_eligible' => true, 'is_commission_threshold_exempt' => true,
            'markup_commission_percent' => 50,
        ]);
        $this->service = Service::create(['brand_id' => $brand->id, 'name' => 'Slip Test Service', 'category' => 'Marketing', 'price' => 500]);
        SalesTarget::create([
            'brand_id' => $brand->id, 'target_month' => '2026-10-01', 'target_type' => 'agent',
            'user_id' => $this->agent->id, 'amount' => 10000,
        ]);
    }

    private function sale(string $date, ?User $agent = null, string $status = 'Payment Success'): SalesActivity
    {
        $agent ??= $this->agent;

        return SalesActivity::create([
            'brand_id' => $agent->brand_id, 'agent_id' => $agent->id, 'service_id' => $this->service->id,
            'activity_type' => 'sale', 'author_name' => 'Author ' . $date . '-' . $agent->id,
            'book_title' => 'Book ' . $date, 'service_name' => $this->service->name,
            'amount' => 1000, 'agent_credit_amount' => 1000,
            'payment_method' => 'Card', 'payment_status' => $status, 'sold_date' => $date,
        ]);
    }

    private function slip(array $query = []): TestResponse
    {
        return $this->withToken('commission-slip-test-token')->getJson(route('api.hris.commission-slip', array_replace([
            'agent' => 'AG-101', 'hris_employee_id' => 'EMP-4415', 'month' => '2026-10',
        ], $query)));
    }

    private function assertSummaryMatchesTransactions(array $json): void
    {
        $rows = collect($json['transactions']);
        foreach ([
            'mtd' => 'sale_amount', 'service_commission' => 'service_commission',
            'markup_commission' => 'markup_commission', 'usd_total' => 'usd_total',
            'php_total' => 'php_total', 'card_payment_hold_amount' => 'card_hold_amount',
            'card_hold_amount' => 'card_hold_amount', 'net_commission' => 'net_commission',
        ] as $summaryKey => $rowKey) {
            $this->assertEquals(round($rows->sum($rowKey), 2), $json['summary'][$summaryKey], $summaryKey);
        }
    }

    public function test_ten_day_slip_limits_transactions_and_every_payroll_total_inclusively(): void
    {
        foreach (['2026-09-30', '2026-10-01', '2026-10-10', '2026-10-14', '2026-10-16', '2026-11-01'] as $date) $this->sale($date);
        $other = User::factory()->create(['brand_id' => $this->agent->brand_id]);
        $this->sale('2026-10-03', $other);
        $this->sale('2026-10-04', null, 'Processing');

        $partial = $this->slip(['from' => '2026-10-01', 'to' => '2026-10-10'])->assertOk()
            ->assertJsonCount(2, 'transactions')->json();
        $this->assertSame(['2026-10-10', '2026-10-01'], array_column($partial['transactions'], 'sold_date'));
        $this->assertSame('2026-10-01', $partial['from']);
        $this->assertSame('2026-10-10', $partial['to']);
        foreach ([
            'mtd' => 2000, 'service_commission' => 150, 'markup_commission' => 500,
            'usd_total' => 650, 'php_total' => 36400, 'card_payment_hold_amount' => 5460,
            'card_hold_amount' => 5460, 'net_commission' => 30940,
        ] as $key => $expected) $this->assertEquals($expected, $partial['summary'][$key], $key);
        $this->assertSummaryMatchesTransactions($partial);

        $full = $this->slip(['from' => '2026-10-01', 'to' => '2026-10-31'])->assertOk()->assertJsonCount(4, 'transactions')->json();
        $this->assertEquals(61880, $full['summary']['net_commission']);
        $this->assertGreaterThan($partial['summary']['net_commission'], $full['summary']['net_commission']);
        $this->assertSummaryMatchesTransactions($full);
    }

    public function test_outside_sales_do_not_inflate_the_range_commission_tier(): void
    {
        SalesTarget::query()->update(['amount' => 3000]);
        foreach (['2026-10-01', '2026-10-10', '2026-10-14', '2026-10-16'] as $date) $this->sale($date);
        $partial = $this->slip(['from' => '2026-10-01', 'to' => '2026-10-10'])->assertOk()->json();
        $full = $this->slip()->assertOk()->json();

        $this->assertEquals(150, $partial['summary']['service_commission']);
        $this->assertEquals(500, $full['summary']['service_commission']);
        $this->assertEquals(66.67, $partial['summary']['mtd_percent']);
        $this->assertSummaryMatchesTransactions($partial);
    }

    public function test_thresholds_are_calculated_from_the_range_not_consumed_by_outside_sales(): void
    {
        $this->agent->update(['is_commission_threshold_exempt' => false, 'commission_threshold_amount' => 500]);
        foreach (['2026-10-01', '2026-10-05', '2026-10-10', '2026-10-16'] as $date) $this->sale($date);
        $partial = $this->slip(['from' => '2026-10-05', 'to' => '2026-10-10'])->assertOk()->json();

        $this->assertEquals(500, $partial['summary']['threshold_applied_amount']);
        $this->assertEquals(75, $partial['summary']['service_commission']);
        $this->assertEquals(575, $partial['summary']['usd_total']);
        $this->assertSummaryMatchesTransactions($partial);
    }

    public function test_empty_and_single_day_ranges_have_matching_totals(): void
    {
        $this->sale('2026-10-10');
        $empty = $this->slip(['from' => '2026-10-01', 'to' => '2026-10-09'])->assertOk()->assertJsonCount(0, 'transactions')->json();
        $this->assertSummaryMatchesTransactions($empty);
        $this->assertEquals(0, $empty['summary']['net_commission']);
        $single = $this->slip(['from' => '2026-10-10', 'to' => '2026-10-10'])->assertOk()->assertJsonCount(1, 'transactions')->json();
        $this->assertEquals(15470, $single['summary']['net_commission']);
        $this->assertSummaryMatchesTransactions($single);
    }

    public function test_month_only_requests_keep_full_month_behavior_and_dates_can_supply_the_month(): void
    {
        $this->sale('2026-10-01');
        $this->sale('2026-10-16');
        $legacy = $this->slip()->assertOk()->assertJsonCount(2, 'transactions')->json();
        $explicit = $this->slip(['from' => '2026-10-01', 'to' => '2026-10-31'])->assertOk()->json();
        $this->assertSame($legacy['summary'], $explicit['summary']);
        $this->assertSame($legacy['transactions'], $explicit['transactions']);
        $this->slip(['month' => null, 'from' => '2026-10-01', 'to' => '2026-10-10'])->assertOk()
            ->assertJsonPath('month', '2026-10')->assertJsonCount(1, 'transactions');
    }

    public function test_invalid_incomplete_and_reversed_ranges_are_rejected(): void
    {
        foreach ([
            [['from' => '2026-10-01'], 'to'],
            [['to' => '2026-10-10'], 'from'],
            [['from' => '2026-10-11', 'to' => '2026-10-10'], 'to'],
            [['from' => '2026-02-30', 'to' => '2026-10-10'], 'from'],
            [['from' => '10/01/2026', 'to' => '2026-10-10'], 'from'],
        ] as [$query, $field]) {
            $this->slip($query)->assertUnprocessable()->assertJsonValidationErrors($field);
        }
    }

    public function test_dispute_rows_outside_the_run_do_not_leak_into_transactions_or_summary(): void
    {
        $this->sale('2026-10-05');
        foreach (['2026-10-02' => -20, '2026-10-14' => -100] as $date => $amount) {
            CommissionAdjustment::create([
                'brand_id' => $this->agent->brand_id, 'agent_id' => $this->agent->id,
                'kind' => 'dispute', 'effective_date' => $date, 'usd_amount' => $amount, 'original_commission' => [],
            ]);
        }

        $partial = $this->slip(['from' => '2026-10-01', 'to' => '2026-10-10'])->assertOk()->assertJsonCount(2, 'transactions')->json();
        $this->assertSame(['2026-10-05', '2026-10-02'], array_column($partial['transactions'], 'sold_date'));
        $this->assertEquals(305, $partial['summary']['usd_total']);
        $this->assertEquals(14350, $partial['summary']['net_commission']);
        $this->assertSummaryMatchesTransactions($partial);
        $later = $this->slip(['from' => '2026-10-11', 'to' => '2026-10-20'])->assertOk()->assertJsonCount(1, 'transactions')->json();
        $this->assertEquals(-5600, $later['summary']['net_commission']);
        $this->assertSummaryMatchesTransactions($later);
    }

    public function test_date_range_does_not_bypass_api_authentication(): void
    {
        $this->getJson(route('api.hris.commission-slip', ['hris_employee_id' => 'EMP-4415', 'from' => '2026-10-01', 'to' => '2026-10-10']))->assertForbidden();
    }

    public function test_shared_sales_use_only_the_selected_agents_in_range_commission(): void
    {
        $main = User::factory()->create(['brand_id' => $this->agent->brand_id]);
        foreach (['2026-10-05', '2026-10-16'] as $date) {
            $this->sale($date, $main)->update([
                'agent_credit_amount' => 500, 'frankie_agent_id' => $this->agent->id,
                'frankie_credit_amount' => 500, 'frankie_commission_percent' => 50,
            ]);
        }

        $partial = $this->slip(['from' => '2026-10-01', 'to' => '2026-10-10'])->assertOk()->assertJsonCount(1, 'transactions')->json();
        $this->assertEquals(500, $partial['summary']['mtd']);
        $this->assertEquals(162.5, $partial['summary']['usd_total']);
        $this->assertEquals(7735, $partial['summary']['net_commission']);
        $this->assertSame('2026-10-05', $partial['transactions'][0]['sold_date']);
    }

    public function test_opening_debt_is_not_repeated_in_a_later_run_within_the_same_month(): void
    {
        CommissionAdjustment::create([
            'brand_id' => $this->agent->brand_id, 'agent_id' => $this->agent->id,
            'kind' => 'dispute', 'effective_date' => '2026-09-15', 'usd_amount' => -300, 'original_commission' => [],
        ]);
        $this->sale('2026-10-05');
        $this->sale('2026-10-16');

        $early = $this->slip(['from' => '2026-10-01', 'to' => '2026-10-10'])->assertOk()->assertJsonCount(2, 'transactions')->json();
        $this->assertEquals(-1330, $early['summary']['net_commission']);
        $this->assertSummaryMatchesTransactions($early);
        $later = $this->slip(['from' => '2026-10-11', 'to' => '2026-10-20'])->assertOk()->assertJsonCount(1, 'transactions')->json();
        $this->assertEquals(15470, $later['summary']['net_commission']);
        $this->assertSummaryMatchesTransactions($later);
    }
}
