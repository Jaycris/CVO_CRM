<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Brand;
use App\Models\CommissionProfile;
use App\Models\Role;
use App\Models\SalesActivity;
use App\Models\SalesTarget;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionScreenParityTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        return [
            '--path' => array_values(array_filter(glob(database_path('migrations/*.php')), fn ($path) => basename($path) !== '2026_09_25_000003_add_monthly_period_to_reward_unlocks.php')),
            '--realpath' => true,
        ];
    }

    public function test_malcolm_screens_match_slip_using_his_saved_tiers_and_threshold(): void
    {
        AppSetting::set('hris_api_token', 'screen-parity-token');
        AppSetting::set('commission_exchange_rate', 61);
        AppSetting::set('card_payment_hold_percent', 15);
        $brand = Brand::updateOrCreate(['imprint_name' => 'Inkspire Media House'], ['is_sales_brand' => true]);
        $role = Role::firstOrCreate(['name' => 'Admin'], ['slug' => 'admin', 'department' => 'Admin']);
        $admin = User::factory()->create(['role_id' => $role->id, 'department' => 'Admin', 'brand_id' => $brand->id]);
        $profile = CommissionProfile::create(['name' => 'Tier 1']);
        foreach ([0 => 12, 75 => 15, 100 => 20, 300 => 25] as $minimum => $percent) {
            $profile->rules()->create(['minimum_mtd_percent' => $minimum, 'commission_percent' => $percent]);
        }
        $agent = User::factory()->create([
            'first_name' => 'Malcolm', 'last_name' => 'Keller', 'department' => 'Sales',
            'brand_id' => $brand->id, 'hris_employee_id' => 'EMP-3183',
            'commission_profile_id' => $profile->id, 'is_commission_eligible' => true,
            'commission_threshold_amount' => 500, 'is_commission_threshold_exempt' => false,
            'markup_commission_percent' => 50, 'work_type' => 'remote',
        ]);
        SalesTarget::create([
            'brand_id' => $brand->id, 'user_id' => $agent->id, 'target_month' => '2026-10-01',
            'target_type' => 'agent', 'amount' => 5000,
        ]);
        foreach ([
            ['SE2026493', '2026-10-14', 'Carl H. Mitchell', 2598, 999],
            ['SE2026170', '2026-10-16', 'Julianna Newland', 1999, 1499],
            ['SE2026306', '2026-10-16', 'Dr Scott M Taubold PH D', 1500, 999],
            ['SE2026596', '2026-10-21', 'Griff Ruby', 2899, 1999],
        ] as [$reference, $date, $author, $amount, $price]) {
            $service = Service::create(['brand_id' => $brand->id, 'name' => $reference, 'category' => 'Marketing', 'price' => $price]);
            SalesActivity::create([
                'brand_id' => $brand->id, 'agent_id' => $agent->id, 'service_id' => $service->id,
                'endorsement_code' => $reference, 'activity_type' => 'payment_success',
                'sold_date' => $date, 'author_name' => $author, 'book_title' => $reference,
                'service_name' => $service->name, 'amount' => $amount, 'agent_credit_amount' => $amount,
                'payment_status' => 'Payment Success', 'payment_method' => $author === 'Griff Ruby' ? 'Invoice' : 'Card',
            ]);
        }

        $slip = $this->withToken('screen-parity-token')->getJson(route('api.hris.commission-slip', [
            'hris_employee_id' => 'EMP-3183', 'month' => '2026-10', 'from' => '2026-10-10', 'to' => '2026-10-24',
        ]))->assertOk()->assertJsonCount(4, 'transactions')->json();
        $this->assertEquals(2749.20, $slip['summary']['usd_total']);
        $this->assertEquals(500, $slip['summary']['threshold_applied_amount']);
        $this->assertEquals(142546.01, $slip['summary']['net_commission']);

        foreach ([null, $brand->id] as $brandId) {
            $filters = array_filter(['month' => '2026-10', 'brand_id' => $brandId, 'agent_id' => $agent->id]);
            $statement = $this->actingAs($admin)->get(route('reports.agent-statements.index', $filters))->assertOk();
            $totals = $statement->viewData('totals');
            foreach (['service_commission', 'markup_commission', 'usd_total', 'php_total', 'net_commission'] as $field) {
                $this->assertEquals($slip['summary'][$field], $totals[$field], "Statement $field");
            }
            $statement->assertSee('$2,749.20')->assertDontSee('$3,124.00')
                ->assertSee('Tier 1')->assertSee('Threshold: $500.00')
                ->assertSee('$500.00 threshold applied')->assertDontSee('No card hold');
            $rows = collect($statement->viewData('rows')->items());
            $this->assertCount(4, $rows);
            $this->assertEquals(500, $rows->sum('threshold_applied_amount'));
            foreach ($rows as $row) $this->assertEquals(20, $row['service_commission_percent']);

            $dashboard = $this->get(route('reports.sales-performance.index', $filters + ['search' => 'Malcolm']))->assertOk();
            $credit = collect($dashboard->viewData('agentRows')->items())->firstWhere('id', $agent->id);
            foreach (['service_commission', 'markup_commission', 'usd_total', 'php_total', 'net_commission', 'threshold_applied_amount'] as $field) {
                $this->assertEquals($slip['summary'][$field], $credit[$field], "Dashboard $field");
            }
            $this->assertSame('Tier 1', $credit['commission_profile_name']);
            $this->assertEquals(20, $credit['service_commission_percent']);
            $dashboard->assertSee('$2,749.20')->assertDontSee('$3,124.00');
        }

        // An intentional exemption changes the base, but must never replace Tier 1 with default 25% tiers.
        $agent->update(['is_commission_threshold_exempt' => true]);
        $exemptSlip = $this->withToken('screen-parity-token')->getJson(route('api.hris.commission-slip', [
            'hris_employee_id' => 'EMP-3183', 'month' => '2026-10',
        ]))->assertOk()->json();
        $this->assertEquals(2849.20, $exemptSlip['summary']['usd_total']);
        $this->assertEquals(0, $exemptSlip['summary']['threshold_applied_amount']);
        $exemptStatement = $this->actingAs($admin)->get(route('reports.agent-statements.index', [
            'month' => '2026-10', 'agent_id' => $agent->id,
        ]))->assertOk()->assertSee('Threshold exempt')->assertSee('$2,849.20');
        $this->assertEquals($exemptSlip['summary']['usd_total'], $exemptStatement->viewData('totals')['usd_total']);

        // Earlier October sales can reproduce the reported 25%/no-threshold rows exactly.
        $agent->update(['is_commission_threshold_exempt' => false]);
        $earlierService = Service::create(['brand_id' => $brand->id, 'name' => 'Earlier sale', 'category' => 'Marketing', 'price' => 500]);
        SalesActivity::create([
            'brand_id' => $brand->id, 'agent_id' => $agent->id, 'service_id' => $earlierService->id,
            'activity_type' => 'payment_success', 'sold_date' => '2026-10-01',
            'author_name' => 'Earlier author', 'service_name' => $earlierService->name,
            'amount' => 7000, 'agent_credit_amount' => 7000,
            'payment_status' => 'Payment Success', 'payment_method' => 'Card',
        ]);
        $fullMonth = $this->get(route('reports.agent-statements.index', [
            'month' => '2026-10', 'agent_id' => $agent->id,
        ]))->assertOk();
        $fourLatest = collect($fullMonth->viewData('rows')->items())
            ->filter(fn ($row) => $row['activity']->sold_date->toDateString() >= '2026-10-10');
        $this->assertEquals(3124, $fourLatest->sum('usd_total'));
        $this->assertEquals(0, $fourLatest->sum('threshold_applied_amount'));

        $range = ['month' => '2026-10', 'from' => '2026-10-10', 'to' => '2026-10-24'];
        $rangeSlip = $this->withToken('screen-parity-token')->getJson(route('api.hris.commission-slip',
            $range + ['hris_employee_id' => 'EMP-3183']))->assertOk()->json();
        foreach ([null, $brand->id] as $brandId) {
            $rangeStatement = $this->actingAs($admin)->get(route('reports.agent-statements.index',
                $range + ['agent_id' => $agent->id, 'brand_id' => $brandId]))->assertOk()
                ->assertSee('$2,749.20')->assertSee('$500.00 threshold applied')
                ->assertSee('2026-10-10 through 2026-10-24')->assertDontSee('Earlier author');
            $this->assertCount(4, $rangeStatement->viewData('rows')->items());
            foreach (['service_commission', 'markup_commission', 'usd_total', 'php_total', 'net_commission'] as $field) {
                $this->assertEquals($rangeSlip['summary'][$field], $rangeStatement->viewData('totals')[$field], "Range statement $field");
            }
            $this->assertEquals(142546.01, $rangeStatement->viewData('totals')['net_commission']);
        }

        $this->get(route('reports.agent-statements.index', [
            'month' => '2026-10', 'agent_id' => $agent->id, 'from' => '2026-10-10', 'to' => '2026-10-10',
        ]))->assertOk()->assertViewHas('totals', fn ($totals) => $totals['usd_total'] == 0);
    }

    public function test_statement_date_range_requires_both_valid_dates_in_order(): void
    {
        $role = Role::firstOrCreate(['name' => 'Admin'], ['slug' => 'admin', 'department' => 'Admin']);
        $admin = User::factory()->create(['role_id' => $role->id, 'department' => 'Admin']);
        foreach ([
            [['from' => '2026-10-10'], 'to'],
            [['to' => '2026-10-24'], 'from'],
            [['from' => '2026-10-24', 'to' => '2026-10-10'], 'to'],
            [['from' => '2026-02-30', 'to' => '2026-10-24'], 'from'],
        ] as [$query, $field]) {
            $this->actingAs($admin)->getJson(route('reports.agent-statements.index', $query))
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->actingAs($admin)->get(route('reports.agent-statements.index', [
            'month' => '2026-10', 'from' => '', 'to' => '',
        ]))->assertOk()->assertViewHas('from', '2026-10-01')->assertViewHas('to', '2026-10-31');
    }
}
