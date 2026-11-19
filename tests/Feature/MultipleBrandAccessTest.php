<?php

namespace Tests\Feature;

use App\Models\{AppSetting, Brand, Department, Lead, Permission, ProductionProject, Role, SalesActivity, SalesEndorsement, SalesTarget, Service, User};
use App\Support\{BrandScope, SalesMtdCalculator};
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Mail, Notification};
use Tests\TestCase;

class MultipleBrandAccessTest extends TestCase
{
    use RefreshDatabase;

    private Brand $inkspire;
    private Brand $horizon;
    private Brand $other;
    private User $admin;
    private User $agent;

    protected function migrateFreshUsing(): array
    {
        return ['--path' => array_values(array_filter(glob(database_path('migrations/*.php')), fn ($path) => basename($path) !== '2026_09_25_000003_add_monthly_period_to_reward_unlocks.php')), '--realpath' => true];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
        Department::firstOrCreate(['name' => 'Sales']);
        $this->inkspire = Brand::updateOrCreate(['imprint_name' => 'Inkspire Media House'], ['is_sales_brand' => true]);
        $this->horizon = Brand::create(['imprint_name' => 'Horizon Maple Media', 'is_sales_brand' => true]);
        $this->other = Brand::create(['imprint_name' => 'Unselected Brand', 'is_sales_brand' => true]);
        $role = Role::firstOrCreate(['name' => 'Admin'], ['slug' => 'admin', 'department' => 'Admin']);
        $this->admin = User::factory()->create(['role_id' => $role->id, 'brand_id' => $this->inkspire->id]);
        $role = Role::create(['name' => 'Shared Agent', 'slug' => 'shared-agent', 'department' => 'Sales']);
        $keys = ['submit_sales_endorsement', 'view_own_sales_endorsements', 'view_unassigned_leads', 'assign_leads', 'view_services_catalog'];
        foreach ($keys as $key) {
            $permission = Permission::firstOrCreate(['key' => $key], ['group' => 'Sales', 'label' => $key]);
            $role->permissionRecords()->syncWithoutDetaching([$permission->id]);
        }
        $this->agent = User::factory()->create(['role_id' => $role->id, 'brand_id' => $this->inkspire->id,
            'department' => 'Sales', 'is_commission_eligible' => true, 'multiple_brands_enabled' => true,
            'commission_threshold_amount' => 500, 'is_commission_threshold_exempt' => false,
            'hris_employee_id' => 'EMP-MULTI', 'markup_commission_percent' => 50]);
        $this->agent->accessibleBrands()->sync([$this->inkspire->id, $this->horizon->id]);
    }

    private function userPayload(array $extra = []): array
    {
        return array_replace(['role_id' => $this->agent->role_id, 'brand_id' => $this->inkspire->id,
            'first_name' => $this->agent->first_name, 'last_name' => $this->agent->last_name,
            'department' => 'Sales', 'email' => $this->agent->email, 'phone_number' => $this->agent->phone_number,
            'is_commission_eligible' => 1, 'multiple_brands_enabled' => 1,
            'allowed_brand_ids' => [$this->inkspire->id, $this->horizon->id]], $extra);
    }

    public function test_profile_enable_disable_and_forms_do_not_move_existing_leads(): void
    {
        $lead = $this->lead($this->horizon);
        $this->actingAs($this->admin)->get(route('admin.users.edit', $this->agent))->assertOk()
            ->assertSee('Enable Multiple Brands')->assertSee('Horizon Maple Media');
        $this->get(route('admin.users.create'))->assertOk()->assertSee('Enable Multiple Brands');
        $this->put(route('admin.users.update', $this->agent), $this->userPayload())->assertSessionHasNoErrors();
        $this->assertTrue($this->agent->fresh()->multiple_brands_enabled);
        $this->assertCount(2, $this->agent->fresh()->accessibleBrands);
        $this->put(route('admin.users.update', $this->agent), $this->userPayload(['multiple_brands_enabled' => 0]))->assertSessionHasNoErrors();
        $fresh = $this->agent->fresh();
        $this->assertFalse($fresh->multiple_brands_enabled);
        $this->assertCount(0, $fresh->accessibleBrands);
        $this->assertFalse(BrandScope::canAccessBrand($fresh, $this->horizon->id));
        $this->assertEquals($this->horizon->id, $lead->fresh()->brand_id);
    }

    public function test_creating_user_saves_selected_brands_with_invitation(): void
    {
        $this->actingAs($this->admin)->post(route('admin.users.store'), $this->userPayload([
            'first_name' => 'New', 'last_name' => 'Shared', 'email' => 'new.shared@example.test', 'phone_number' => '555999888',
            'brand_emails' => [$this->inkspire->id => 'new@inkspire.test', $this->horizon->id => 'new@horizon.test'],
        ]))->assertSessionHasNoErrors();
        $user = User::where('email', 'new.shared@example.test')->firstOrFail();
        $this->assertTrue($user->multiple_brands_enabled);
        $this->assertCount(2, $user->accessibleBrands);
        $this->assertSame('new@horizon.test', $user->emailForBrand($this->horizon->id));
        $this->assertSame('new.shared@example.test', $user->email);
        Mail::assertSent(\App\Mail\UserInvitationMail::class);
    }

    public function test_brand_email_addresses_save_independently_of_login_and_reject_invalid_or_unselected_addresses(): void
    {
        $login = $this->agent->email;
        $emails = [$this->inkspire->id => 'agent@inkspire.test', $this->horizon->id => 'Agent@Horizon.test'];
        $this->actingAs($this->admin)->put(route('admin.users.update', $this->agent), $this->userPayload(['brand_emails' => $emails]))->assertSessionHasNoErrors();
        $fresh = $this->agent->fresh();
        $this->assertSame($login, $fresh->email);
        $this->assertSame('agent@inkspire.test', $fresh->emailForBrand($this->inkspire->id));
        $this->assertSame('agent@horizon.test', $fresh->emailForBrand($this->horizon->id));
        $this->assertNull($fresh->emailForBrand($this->other->id));
        $this->get(route('admin.users.edit', $this->agent))->assertOk()->assertSee('agent@horizon.test')->assertSee('Login Email Address');
        $this->put(route('admin.users.update', $this->agent), $this->userPayload(['brand_emails' => [$this->horizon->id => 'invalid']]))->assertSessionHasErrors('brand_emails.'.$this->horizon->id);
        $this->put(route('admin.users.update', $this->agent), $this->userPayload(['brand_emails' => [$this->other->id => 'agent@other.test']]))->assertSessionHasErrors('brand_emails');
        $this->assertSame('agent@horizon.test', $this->agent->fresh()->emailForBrand($this->horizon->id));
    }

    public function test_invalid_selections_and_unauthorized_updates_cannot_grant_access(): void
    {
        $this->actingAs($this->admin);
        foreach ([[], [$this->horizon->id], [$this->horizon->id, $this->other->id], [$this->inkspire->id, 999999]] as $ids) {
            $this->put(route('admin.users.update', $this->agent), $this->userPayload(['allowed_brand_ids' => $ids]))->assertSessionHasErrors();
            $this->assertSame([$this->inkspire->id, $this->horizon->id], $this->agent->fresh()->accessibleBrands->pluck('id')->sort()->values()->all());
        }
        $this->actingAs($this->agent)->put(route('admin.users.update', $this->agent), $this->userPayload([
            'allowed_brand_ids' => [$this->inkspire->id, $this->other->id],
        ]))->assertForbidden();
        $this->assertFalse(BrandScope::canAccessBrand($this->agent->fresh(), $this->other->id));
    }

    private function lead(Brand $brand): Lead
    {
        return Lead::create(['brand_id' => $brand->id, 'created_by' => $this->agent->id,
            'book_title' => 'Book '.$brand->id, 'author_name' => 'Author '.$brand->id, 'phone_numbers' => ['555'.$brand->id],
            'lead_generation_stage' => 'ready_to_assign', 'verify_score' => 100, 'verified_at' => now()]);
    }

    public function test_lead_visibility_and_assignment_preserve_brand_and_reject_unselected_brand(): void
    {
        $inkspire = $this->lead($this->inkspire);
        $horizon = $this->lead($this->horizon);
        $other = $this->lead($this->other);
        $response = $this->actingAs($this->agent)->get(route('leads.unassigned'))->assertOk();
        $this->assertEqualsCanonicalizing([$inkspire->id, $horizon->id], $response->viewData('leads')->pluck('id')->all());
        $this->post(route('leads.assign'), ['lead_ids' => [$horizon->id], 'assigned_to' => $this->agent->id])->assertSessionHasNoErrors();
        $this->assertEquals($this->agent->id, $horizon->fresh()->assigned_to);
        $this->assertEquals($this->horizon->id, $horizon->fresh()->brand_id);
        $this->post(route('leads.assign'), ['lead_ids' => [$other->id], 'assigned_to' => $this->agent->id])->assertForbidden();
        $this->assertNull($other->fresh()->assigned_to);
    }

    private function salePayload(Service $service): array
    {
        return ['brand_id' => $service->brand_id, 'service_id' => $service->id, 'markup_amount' => 100,
            'author_name' => 'Shared Author', 'contact_number' => '5550100', 'street_name' => 'Street',
            'city_state' => 'City', 'zip_code' => '1000', 'book_title' => 'Shared Book', 'isbn' => '123', 'payment' => 'Full Payment'];
    }

    public function test_endorsement_brand_and_services_are_checked_on_server(): void
    {
        $service = Service::create(['brand_id' => $this->horizon->id, 'name' => 'Horizon service', 'category' => 'Publishing', 'price' => 1000]);
        $other = Service::create(['brand_id' => $this->other->id, 'name' => 'Forbidden service', 'category' => 'Publishing', 'price' => 1000]);
        $this->actingAs($this->agent)->get(route('sales.endorsements.create'))->assertOk()->assertSee('Sales Brand')->assertDontSee('Forbidden service');
        $this->post(route('sales.endorsements.store'), $this->salePayload($service))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('sales_endorsements', ['agent_id' => $this->agent->id, 'brand_id' => $this->horizon->id, 'amount' => 1100]);
        $this->post(route('sales.endorsements.store'), array_replace($this->salePayload($service), ['brand_id' => $this->inkspire->id]))->assertSessionHasErrors('service_id');
        $this->post(route('sales.endorsements.store'), $this->salePayload($other))->assertForbidden();
    }

    public function test_combined_target_tier_threshold_and_api_agree_with_brand_filtered_reports(): void
    {
        AppSetting::set('hris_api_token', 'multi-token');
        AppSetting::set('commission_exchange_rate', 60);
        SalesTarget::create(['brand_id' => $this->inkspire->id, 'user_id' => $this->agent->id,
            'target_month' => '2026-11-01', 'target_type' => 'agent', 'amount' => 5000]);
        foreach ([$this->inkspire, $this->horizon] as $index => $brand) {
            $service = Service::create(['brand_id' => $brand->id, 'name' => 'Monthly service '.$brand->id, 'category' => 'Marketing', 'price' => 2000]);
            SalesActivity::create(['brand_id' => $brand->id, 'agent_id' => $this->agent->id, 'service_id' => $service->id,
                'activity_type' => 'payment_success', 'sold_date' => '2026-11-'.($index ? '12' : '05'), 'author_name' => 'Monthly Author',
                'service_name' => $service->name, 'amount' => 3000, 'agent_credit_amount' => 3000, 'payment_status' => 'Payment Success', 'payment_method' => 'Card']);
        }
        $month = Carbon::parse('2026-11-01');
        $all = SalesMtdCalculator::summary($this->agent, $month)['agentCredits']->get($this->agent->id);
        $this->assertEquals(6000, $all['mtd']);
        $this->assertEquals(25, $all['service_commission_percent']);
        $this->assertEquals(500, $all['threshold_applied_amount']);
        $this->assertEquals(1875, $all['usd_total']);
        $brandTotals = 0;
        foreach ([$this->inkspire, $this->horizon] as $brand) {
            $credit = SalesMtdCalculator::summary($this->agent, $month, $brand->id)['agentCredits']->get($this->agent->id);
            $this->assertEquals(25, $credit['service_commission_percent']);
            $brandTotals += $credit['usd_total'];
            $page = $this->actingAs($this->agent)->get(route('reports.agent-statements.index', ['month' => '2026-11', 'brand_id' => $brand->id]))->assertOk();
            $this->assertEquals($credit['usd_total'], $page->viewData('totals')['usd_total']);
            $this->assertEquals(5000, $page->viewData('totals')['target']);
        }
        $this->assertEquals($all['usd_total'], $brandTotals);
        $slip = $this->withToken('multi-token')->getJson(route('api.hris.commission-slip', ['hris_employee_id' => 'EMP-MULTI', 'month' => '2026-11']))->assertOk()->json();
        $this->assertEquals($all['usd_total'], $slip['summary']['usd_total']);
        $this->assertEquals(500, $slip['summary']['threshold_applied_amount']);
        $range = $this->withToken('multi-token')->getJson(route('api.hris.commission-slip', ['hris_employee_id' => 'EMP-MULTI', 'month' => '2026-11', 'from' => '2026-11-10', 'to' => '2026-11-20']))->assertOk()->json();
        $this->assertCount(1, $range['transactions']);
        $this->assertEquals(1000, $range['summary']['usd_total']);
        $this->assertEquals(0, $range['summary']['threshold_applied_amount']);
        $dashboard = $this->actingAs($this->agent)->get(route('reports.sales-performance.index', ['month' => '2026-11']))->assertOk();
        $credit = collect($dashboard->viewData('agentRows')->items())->firstWhere('id', $this->agent->id);
        $this->assertEquals($all['usd_total'], $credit['usd_total']);
        $this->get(route('reports.agent-statements.index', ['brand_id' => $this->other->id]))->assertForbidden();

        \App\Models\SalesMtdDeduction::create(['request_key' => (string) \Illuminate\Support\Str::uuid(),
            'agent_id' => $this->agent->id, 'brand_id' => $this->inkspire->id, 'effective_month' => '2026-11-01',
            'amount' => 1500, 'reason' => 'Combined MTD adjustment', 'created_by' => $this->admin->id]);
        $adjusted = SalesMtdCalculator::summary($this->agent, $month)['agentCredits']->get($this->agent->id);
        $this->assertEquals(4500, $adjusted['mtd']);
        $this->assertEquals(20, $adjusted['service_commission_percent']);
        $horizon = SalesMtdCalculator::summary($this->agent, $month, $this->horizon->id)['agentCredits']->get($this->agent->id);
        $this->assertEquals(20, $horizon['service_commission_percent']);
        $adjustedSlip = $this->withToken('multi-token')->getJson(route('api.hris.commission-slip', ['hris_employee_id' => 'EMP-MULTI', 'month' => '2026-11']))->assertOk()->json();
        $this->assertEquals($adjusted['usd_total'], $adjustedSlip['summary']['usd_total']);
    }

    public function test_shared_production_staff_see_only_eligible_brands_and_keep_role_permissions(): void
    {
        $role = Role::firstOrCreate(['name' => 'Fulfillment Officer'], ['slug' => 'fulfillment-officer', 'department' => 'Production']);
        $permission = Permission::firstOrCreate(['key' => 'view_all_fulfillment_trackers'], ['group' => 'Production', 'label' => 'View All Fulfillment Trackers']);
        $role->permissionRecords()->syncWithoutDetaching([$permission->id]);
        $officer = User::factory()->create(['brand_id' => $this->inkspire->id, 'role_id' => $role->id,
            'department' => 'Production', 'multiple_brands_enabled' => true]);
        $officer->accessibleBrands()->sync([$this->inkspire->id, $this->horizon->id]);
        foreach ([$this->inkspire, $this->horizon, $this->other] as $brand) {
            $endorsement = SalesEndorsement::create(['brand_id' => $brand->id, 'agent_id' => $this->agent->id,
                'author_name' => 'Project '.$brand->id, 'contact_number' => '5550100', 'street_name' => 'Street', 'city_state' => 'City', 'zip_code' => '1000', 'isbn' => '123',
                'book_title' => 'Book', 'services' => 'Publishing', 'amount' => 1000, 'amount_to_be_paid' => 1000, 'payment' => 'Full Payment']);
            ProductionProject::create(['brand_id' => $brand->id, 'sales_endorsement_id' => $endorsement->id, 'tracker_type' => 'publishing', 'status' => 'pending']);
        }
        $response = $this->actingAs($officer)->get(route('production.projects.index'))->assertOk();
        $this->assertEqualsCanonicalizing([$this->inkspire->id, $this->horizon->id], $response->viewData('projects')->pluck('brand_id')->all());
        $ids = ProductionProject::whereIn('brand_id', [$this->inkspire->id, $this->horizon->id])->pluck('id')->all();
        $this->actingAs($this->admin)->put(route('production.projects.bulk-assign'), ['project_ids' => $ids, 'assigned_to' => $officer->id])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertEquals(2, ProductionProject::where('assigned_to', $officer->id)->count());
        $third = ProductionProject::where('brand_id', $this->other->id)->firstOrFail();
        $this->put(route('production.projects.bulk-assign'), ['project_ids' => [$third->id], 'assigned_to' => $officer->id])->assertSessionHas('error');
        $this->assertNull($third->fresh()->assigned_to);
        $this->actingAs($this->agent)->get(route('production.projects.index'))->assertForbidden();
    }

    public function test_verification_routes_each_brand_only_to_an_eligible_verifier(): void
    {
        $role = Role::create(['name' => 'Shared Verifier Test', 'slug' => 'shared-verifier-test', 'department' => 'Lead Generation']);
        foreach (['view_verification_queue', 'verify_leads'] as $key) {
            $permission = Permission::firstOrCreate(['key' => $key], ['group' => 'Leads', 'label' => $key]);
            $role->permissionRecords()->syncWithoutDetaching([$permission->id]);
        }
        $verifier = User::factory()->create(['brand_id' => $this->inkspire->id, 'role_id' => $role->id, 'department' => 'Lead Generation']);
        $first = $this->lead($this->inkspire);
        $second = $this->lead($this->horizon);
        $this->actingAs($this->admin)->post(route('leads.send-to-verification'), ['lead_ids' => [$first->id, $second->id]])->assertSessionHas('error');
        $this->assertSame('ready_to_assign', $first->fresh()->lead_generation_stage);
        $this->assertNull($second->fresh()->verification_assigned_to);
        $verifier->update(['multiple_brands_enabled' => true]);
        $verifier->accessibleBrands()->sync([$this->inkspire->id, $this->horizon->id]);
        $this->post(route('leads.send-to-verification'), ['lead_ids' => [$first->id, $second->id]])->assertSessionHas('success');
        $this->assertEquals($verifier->id, $first->fresh()->verification_assigned_to);
        $this->assertEquals($verifier->id, $second->fresh()->verification_assigned_to);
    }
}
