<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Role;
use App\Models\SalesActivity;
use App\Models\SalesTarget;
use App\Models\Service;
use App\Models\Team;
use App\Models\User;
use App\Support\SalesMtdCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SalesBrandTargetTest extends TestCase
{
    use RefreshDatabase;

    private Brand $brand;
    private User $admin;

    protected function migrateFreshUsing(): array
    {
        return ['--path' => array_values(array_filter(glob(database_path('migrations/*.php')), fn ($path) => basename($path) !== '2026_09_25_000003_add_monthly_period_to_reward_unlocks.php')), '--realpath' => true];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-06 12:00:00'));
        $this->brand = Brand::firstOrCreate(['imprint_name' => 'Inkspire Media House']);
        $this->brand->update(['is_sales_brand' => true]);
        $role = Role::firstOrCreate(['name' => 'Admin'], ['slug' => 'admin', 'department' => 'Admin']);
        $this->admin = User::factory()->create(['brand_id' => $this->brand->id, 'role_id' => $role->id, 'department' => 'Admin']);
        $this->actingAs($this->admin);
    }

    private function target(string $name, float $amount, ?Brand $brand = null, array $teamFields = [], string $month = '2026-11-01'): SalesTarget
    {
        $brand ??= $this->brand;
        $team = Team::create($teamFields + ['brand_id' => $brand->id, 'department' => 'Sales', 'name' => $name, 'show_on_sales_dashboard' => true]);
        return SalesTarget::create(['brand_id' => $brand->id, 'team_id' => $team->id, 'target_type' => 'team', 'target_month' => $month, 'amount' => $amount]);
    }

    private function sale(float $amount): void
    {
        $agent = User::factory()->create(['brand_id' => $this->brand->id, 'department' => 'Sales', 'is_commission_eligible' => true]);
        $service = Service::create(['brand_id' => $this->brand->id, 'name' => 'Snapshot Service', 'category' => 'Marketing', 'price' => 2999]);
        SalesActivity::create(['brand_id' => $this->brand->id, 'agent_id' => $agent->id, 'service_id' => $service->id,
            'activity_type' => 'payment_success', 'author_name' => 'Snapshot Author', 'book_title' => 'Snapshot Book',
            'amount' => $amount, 'agent_credit_amount' => $amount, 'payment_status' => 'Payment Success', 'sold_date' => '2026-11-05', 'payment_method' => 'Invoice']);
    }

    public function test_home_brand_progress_matches_global_page_using_the_two_monthly_team_targets(): void
    {
        $this->target('TEAM DESTINY', 30000);
        $this->target('TEAM LEGACY', 30000);
        $this->sale(5398);
        $home = $this->get(route('dashboard'))->assertOk()->assertSee('9.00%')->assertSee('of $60,000.00 target');
        $snapshot = $home->viewData('salesMtdBrandSnapshots')->first(fn ($snapshot) => $snapshot['brand']->id === $this->brand->id)['summary'];
        $report = $this->get(route('reports.sales-performance.index', ['month' => '2026-11', 'brand_id' => $this->brand->id]))->assertOk();
        $this->assertEquals($snapshot, $report->viewData('summary')['global']);
        $this->assertEquals(['mtd' => 5398, 'target' => 60000, 'remaining' => 54602, 'percent' => 9], $snapshot);
    }

    public function test_brand_target_excludes_other_brands_other_months_hidden_teams_and_old_global_targets(): void
    {
        $this->target('TEAM DESTINY', 30000);
        $this->target('TEAM LEGACY', 30000);
        $this->target('Old Month', 90000, month: '2026-10-01');
        $this->target('Hidden Team', 90000, teamFields: ['show_on_sales_dashboard' => false]);
        $this->target('Non-Sales Department', 90000, teamFields: ['department' => 'Admin']);
        $other = Brand::create(['imprint_name' => 'Other Sales Brand', 'is_sales_brand' => true]);
        $this->target('Other Brand Team', 10000, $other);
        $nonSales = Brand::create(['imprint_name' => 'Other Department Brand', 'is_sales_brand' => false]);
        $this->target('Non Sales Brand Team', 90000, $nonSales);
        SalesTarget::create(['brand_id' => $this->brand->id, 'target_type' => 'global', 'target_month' => '2026-11-01', 'amount' => 120000]);
        $summary = SalesMtdCalculator::summary($this->admin, now(), $this->brand->id);
        $this->assertEquals(60000, $summary['global']['target']);
        $this->assertEquals(70000, SalesMtdCalculator::summary($this->admin, now(), null, false, true)['global']['target']);
        $this->get(route('reports.sales-performance.index', ['month' => '2026-11', 'brand_id' => $this->brand->id]))->assertOk()
            ->assertViewHas('summary', fn ($summary) => $summary['global']['target'] == 60000);
    }

    public function test_missing_target_is_shown_as_unset_and_saving_team_targets_updates_both_pages(): void
    {
        $target = $this->target('TEAM DESTINY', 0);
        $this->sale(5398);
        $home = $this->get(route('dashboard'))->assertOk()->assertSee('Target not set')->assertSee('Set monthly team targets');
        $this->assertEquals(0, $home->viewData('salesMtdBrandSnapshots')->first(fn ($snapshot) => $snapshot['brand']->id === $this->brand->id)['summary']['target']);
        $this->put(route('reports.sales-performance.targets'), ['month' => '2026-11', 'brand_id' => $this->brand->id,
            'team_targets' => [$target->team_id => 30000]])->assertSessionHasNoErrors();
        $this->get(route('dashboard'))->assertOk()->assertSee('17.99%')->assertSee('of $30,000.00 target');
        $this->get(route('reports.sales-performance.index', ['month' => '2026-11', 'brand_id' => $this->brand->id]))->assertOk()
            ->assertViewHas('summary', fn ($summary) => $summary['global']['target'] == 30000 && $summary['global']['percent'] == 17.99);
    }
}
