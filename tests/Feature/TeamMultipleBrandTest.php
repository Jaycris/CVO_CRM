<?php

namespace Tests\Feature;

use App\Models\{Brand, Department, Lead, Role, SalesActivity, SalesTarget, Service, Team, User};
use App\Support\{BrandScope, SalesMtdCalculator};
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamMultipleBrandTest extends TestCase
{
    use RefreshDatabase;

    private Brand $inkspire;
    private Brand $horizon;
    private Brand $other;
    private User $admin;
    private User $inkspireUser;
    private User $horizonUser;

    protected function migrateFreshUsing(): array
    {
        return ['--path' => array_values(array_filter(glob(database_path('migrations/*.php')), fn ($path) => basename($path) !== '2026_09_25_000003_add_monthly_period_to_reward_unlocks.php')), '--realpath' => true];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Department::firstOrCreate(['name' => 'Sales']);
        Department::firstOrCreate(['name' => 'Administration']);
        $this->inkspire = Brand::updateOrCreate(['imprint_name' => 'Inkspire Media House'], ['is_sales_brand' => true]);
        $this->horizon = Brand::create(['imprint_name' => 'Horizon Maple Media', 'is_sales_brand' => true]);
        $this->other = Brand::create(['imprint_name' => 'Unselected Brand', 'is_sales_brand' => true]);
        $role = Role::firstOrCreate(['name' => 'Admin'], ['slug' => 'admin', 'department' => 'Admin']);
        $this->admin = User::factory()->create(['role_id' => $role->id, 'brand_id' => $this->inkspire->id]);
        $this->inkspireUser = User::factory()->create(['brand_id' => $this->inkspire->id, 'department' => 'Sales', 'is_commission_eligible' => true]);
        $this->horizonUser = User::factory()->create(['brand_id' => $this->horizon->id, 'department' => 'Sales', 'is_commission_eligible' => true]);
        $this->actingAs($this->admin);
    }

    private function payload(array $extra = []): array
    {
        return array_replace(['brand_id' => $this->inkspire->id, 'name' => 'Shared Sales Team', 'department' => 'Sales',
            'multiple_brands_enabled' => 1, 'allowed_brand_ids' => [$this->inkspire->id, $this->horizon->id],
            'member_ids' => [$this->inkspireUser->id, $this->horizonUser->id], 'show_on_sales_dashboard' => 1], $extra);
    }

    public function test_shared_team_can_include_members_from_both_brands_without_granting_user_access_or_moving_leads(): void
    {
        $lead = Lead::create(['brand_id' => $this->horizon->id, 'author_name' => 'Team Lead', 'book_title' => 'Team Book',
            'phone_numbers' => ['5550100'], 'assigned_to' => $this->horizonUser->id]);
        $this->post(route('admin.teams.store'), $this->payload())->assertSessionHasNoErrors();
        $team = Team::where('name', 'Shared Sales Team')->firstOrFail();
        $this->assertTrue($team->multiple_brands_enabled);
        $this->assertEqualsCanonicalizing([$this->inkspire->id, $this->horizon->id], $team->accessibleBrands->pluck('id')->all());
        $this->assertCount(2, $team->members);
        $this->assertEquals($this->horizon->id, $this->horizonUser->fresh()->brand_id);
        $this->assertFalse(BrandScope::canAccessBrand($this->inkspireUser->fresh(), $this->horizon->id));
        $this->assertEquals($this->horizon->id, $lead->fresh()->brand_id);
        $this->assertEquals($this->horizonUser->id, $lead->fresh()->assigned_to);
        $this->get(route('admin.teams.index'))->assertOk()->assertSee('Enable Multiple Brands')->assertSee('Horizon Maple Media');
    }

    public function test_invalid_brand_or_department_members_do_not_partially_change_the_team(): void
    {
        $this->post(route('admin.teams.store'), $this->payload())->assertSessionHasNoErrors();
        $team = Team::where('name', 'Shared Sales Team')->firstOrFail();
        $outside = User::factory()->create(['brand_id' => $this->other->id, 'department' => 'Sales']);
        foreach ([['member_ids' => [$outside->id]], ['manager_id' => $outside->id],
            ['allowed_brand_ids' => [$this->inkspire->id]], ['allowed_brand_ids' => [$this->horizon->id, $this->other->id]],
            ['multiple_brands_enabled' => 0], ['department' => 'Administration']] as $invalid) {
            $this->put(route('admin.teams.update', $team), $this->payload($invalid))->assertSessionHasErrors();
            $this->assertTrue($team->fresh()->multiple_brands_enabled);
            $this->assertCount(2, $team->fresh()->members);
            $this->assertCount(2, $team->fresh()->accessibleBrands);
        }
        $this->assertNull($outside->fresh()->team_id);
    }

    public function test_shared_user_can_join_a_secondary_brand_team_and_disabling_team_multi_brand_preserves_records(): void
    {
        $this->inkspireUser->update(['multiple_brands_enabled' => true]);
        $this->inkspireUser->accessibleBrands()->sync([$this->inkspire->id, $this->horizon->id]);
        $this->post(route('admin.teams.store'), $this->payload(['brand_id' => $this->horizon->id,
            'multiple_brands_enabled' => 0, 'member_ids' => [$this->inkspireUser->id]]))->assertSessionHasNoErrors();
        $team = Team::where('name', 'Shared Sales Team')->firstOrFail();
        $this->assertFalse($team->multiple_brands_enabled);
        $this->assertCount(0, $team->accessibleBrands);
        $this->assertEquals($team->id, $this->inkspireUser->fresh()->team_id);
        $this->assertEquals($this->inkspire->id, $this->inkspireUser->fresh()->brand_id);
    }

    public function test_team_brand_scope_matches_either_selected_brand_and_does_not_grant_management_permission(): void
    {
        $this->post(route('admin.teams.store'), $this->payload())->assertSessionHasNoErrors();
        $team = Team::where('name', 'Shared Sales Team')->firstOrFail();
        $this->assertTrue(BrandScope::apply(Team::query(), $this->horizonUser)->whereKey($team->id)->exists());
        $outside = User::factory()->create(['brand_id' => $this->other->id]);
        $this->assertFalse(BrandScope::apply(Team::query(), $outside)->whereKey($team->id)->exists());
        $this->actingAs($this->horizonUser)->put(route('admin.teams.update', $team), $this->payload())->assertForbidden();
    }

    private function sharedSalesTeam(): Team
    {
        $this->post(route('admin.teams.store'), $this->payload())->assertSessionHasNoErrors();
        return Team::where('name', 'Shared Sales Team')->firstOrFail();
    }

    private function sale(User $agent, Brand $brand, float $amount): void
    {
        $service = Service::create(['brand_id' => $brand->id, 'name' => 'Team service '.$brand->id.' '.$amount, 'category' => 'Marketing', 'price' => $amount]);
        SalesActivity::create(['brand_id' => $brand->id, 'agent_id' => $agent->id, 'service_id' => $service->id,
            'activity_type' => 'payment_success', 'author_name' => 'Team sales author', 'book_title' => 'Sales book',
            'service_name' => $service->name, 'amount' => $amount, 'agent_credit_amount' => $amount,
            'payment_status' => 'Payment Success', 'sold_date' => '2026-11-05', 'payment_method' => 'Invoice']);
    }

    public function test_separate_brand_targets_sales_and_home_cards_reconcile_to_global_once(): void
    {
        $this->travelTo(Carbon::parse('2026-11-21 12:00:00'));
        $team = $this->sharedSalesTeam();
        $this->put(route('reports.sales-performance.targets'), ['month' => '2026-11',
            'team_brand_targets' => [$team->id => [$this->inkspire->id => '10,000.00', $this->horizon->id => '20,000.00']]])->assertSessionHasNoErrors();
        $this->assertEquals(2, SalesTarget::where('team_id', $team->id)->count());
        $this->sale($this->inkspireUser, $this->inkspire, 1000);
        $this->sale($this->inkspireUser, $this->horizon, 2000);
        $this->sale($this->horizonUser, $this->horizon, 3000);
        // Old global/null duplicates and invalid brand allocations must not inflate the total.
        SalesTarget::create(['team_id' => $team->id, 'brand_id' => null, 'target_type' => 'team', 'target_month' => '2026-11-01', 'amount' => 999999]);
        SalesTarget::create(['team_id' => $team->id, 'brand_id' => $this->other->id, 'target_type' => 'team', 'target_month' => '2026-11-01', 'amount' => 999999]);
        $page = $this->get(route('reports.sales-performance.index', ['month' => '2026-11']))->assertOk();
        $this->assertEquals(30000, $page->viewData('summary')['global']['target']);
        $this->assertEquals(6000, $page->viewData('summary')['global']['mtd']);
        $this->assertCount(2, $page->viewData('targetTeamRows'));
        $rows = $page->viewData('teamRows')->keyBy('brand_id');
        $this->assertEquals(1000, $rows[$this->inkspire->id]['mtd']);
        $this->assertEquals(5000, $rows[$this->horizon->id]['mtd']);
        $this->assertEquals(10, $rows[$this->inkspire->id]['percent']);
        $this->assertEquals(25, $rows[$this->horizon->id]['percent']);
        $this->assertEquals(1, $rows[$this->inkspire->id]['members']);
        $this->assertEquals(1, $rows[$this->horizon->id]['members']);
        $this->assertSame(1, substr_count($page->getContent(), 'data-team-target="'.$team->id.'"'));
        foreach ([[$this->inkspire, 1000, 10000], [$this->horizon, 5000, 20000]] as [$brand, $mtd, $target]) {
            $filtered = $this->get(route('reports.sales-performance.index', ['month' => '2026-11', 'brand_id' => $brand->id]))->assertOk();
            $this->assertCount(1, $filtered->viewData('teamRows'));
            $this->assertEquals($mtd, $filtered->viewData('summary')['global']['mtd']);
            $this->assertEquals($target, $filtered->viewData('summary')['global']['target']);
        }
        $home = $this->get(route('dashboard'))->assertOk();
        $cards = $home->viewData('homeTeamSalesMtdSnapshots')->keyBy('brand.id');
        $this->assertCount(2, $cards);
        $this->assertEquals(10000, $cards[$this->inkspire->id]['target']);
        $this->assertEquals(20000, $cards[$this->horizon->id]['target']);
        $this->assertEquals(5000, $cards[$this->horizon->id]['mtd']);
        $this->assertEquals(1, $cards[$this->inkspire->id]['members']);
        $this->assertEquals(1, $cards[$this->horizon->id]['members']);
        $this->assertSame(1, substr_count($home->getContent(), 'data-team-snapshot="'.$team->id.'"'));
        $home->assertSee('Inkspire Media House')->assertSee('Horizon Maple Media');
        $this->assertEquals(30000, $home->viewData('homeSalesMtdSummary')['global']['target']);

        $this->inkspireUser->update(['multiple_brands_enabled' => true]);
        $this->inkspireUser->accessibleBrands()->sync([$this->inkspire->id, $this->horizon->id]);
        $updated = $this->get(route('dashboard'))->assertOk()->viewData('homeTeamSalesMtdSnapshots')->keyBy('brand.id');
        $this->assertEquals(1, $updated[$this->inkspire->id]['members']);
        $this->assertEquals(2, $updated[$this->horizon->id]['members']);
    }

    public function test_brand_filtered_updates_leave_the_other_target_unchanged_and_reject_invalid_batch_atomically(): void
    {
        $team = $this->sharedSalesTeam();
        foreach ([[$this->inkspire, 10000], [$this->horizon, 20000]] as [$brand, $target]) {
            $this->put(route('reports.sales-performance.targets'), ['month' => '2026-11', 'brand_id' => $brand->id,
                'team_brand_targets' => [$team->id => [$brand->id => $target]]])->assertSessionHasNoErrors();
        }
        $this->put(route('reports.sales-performance.targets'), ['month' => '2026-11', 'brand_id' => $this->horizon->id,
            'team_targets' => [$team->id => 25000]])->assertSessionHasNoErrors();
        $this->assertEquals(10000, SalesTarget::where('team_id', $team->id)->where('brand_id', $this->inkspire->id)->value('amount'));
        $this->assertEquals(25000, SalesTarget::where('team_id', $team->id)->where('brand_id', $this->horizon->id)->value('amount'));
        $this->put(route('reports.sales-performance.targets'), ['month' => '2026-11', 'team_brand_targets' =>
            [$team->id => [$this->inkspire->id => 50000, $this->other->id => 90000]]])->assertSessionHasErrors('team_brand_targets');
        $this->assertEquals(10000, SalesTarget::where('team_id', $team->id)->where('brand_id', $this->inkspire->id)->value('amount'));
        $this->assertDatabaseCount('sales_targets', 2);
        $this->assertEquals(35000, SalesMtdCalculator::summary($this->admin, Carbon::parse('2026-11-01'), null, false, true)['global']['target']);
    }

    public function test_parent_primary_team_can_set_separate_targets_for_its_selected_sales_brands(): void
    {
        $parent = Brand::firstOrCreate(['imprint_name' => BrandScope::PARENT_BRAND]);
        $parent->update(['is_sales_brand' => false]);
        $this->post(route('admin.teams.store'), $this->payload(['brand_id' => $parent->id]))->assertSessionHasNoErrors();
        $team = Team::where('name', 'Shared Sales Team')->firstOrFail();
        $this->put(route('reports.sales-performance.targets'), ['month' => '2026-11',
            'team_brand_targets' => [$team->id => [$this->inkspire->id => 10000, $this->horizon->id => 20000]]])->assertSessionHasNoErrors();
        $page = $this->get(route('reports.sales-performance.index', ['month' => '2026-11']))->assertOk();
        $this->assertCount(2, $page->viewData('teamRows'));
        $this->assertEquals(30000, $page->viewData('summary')['global']['target']);
    }

    public function test_target_manager_cannot_update_an_unpermitted_brand_even_on_a_shared_team(): void
    {
        $team = $this->sharedSalesTeam();
        $role = Role::create(['name' => 'Restricted Target Manager', 'slug' => 'restricted-target-manager', 'department' => 'Sales']);
        $permission = \App\Models\Permission::firstOrCreate(['key' => 'manage_sales_targets'], ['group' => 'Sales', 'label' => 'Manage Sales Targets']);
        $role->permissionRecords()->sync([$permission->id]);
        $this->inkspireUser->update(['role_id' => $role->id]);
        $this->actingAs($this->inkspireUser)->put(route('reports.sales-performance.targets'), ['month' => '2026-11', 'brand_id' => $this->horizon->id,
            'team_brand_targets' => [$team->id => [$this->horizon->id => 20000]]])->assertForbidden();
        $this->put(route('reports.sales-performance.targets'), ['month' => '2026-11',
            'team_brand_targets' => [$team->id => [$this->inkspire->id => 10000, $this->horizon->id => 20000]]])->assertSessionHasErrors();
        $this->assertDatabaseCount('sales_targets', 0);
    }
}
