<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Lead;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignedLeadPortfolioTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_agent_keeps_staged_leads_in_assigned_portfolio_but_not_new_leads(): void
    {
        $brand = Brand::firstOrCreate(
            ['imprint_name' => 'CreatiVision Outsourcing'],
            ['description' => 'Parent company']
        );
        $permission = Permission::where('key', 'view_assigned_leads')->firstOrFail();
        $salesRole = Role::firstOrCreate(
            ['name' => 'Portfolio Test Sales'],
            ['department' => 'Sales', 'slug' => 'portfolio-test-sales']
        );
        $salesRole->permissionRecords()->syncWithoutDetaching([$permission->id]);

        $agent = User::factory()->create([
            'role_id' => $salesRole->id,
            'brand_id' => $brand->id,
            'department' => 'Sales',
        ]);
        $otherAgent = User::factory()->create([
            'role_id' => $salesRole->id,
            'brand_id' => $brand->id,
            'department' => 'Sales',
        ]);

        Lead::create([
            'brand_id' => $brand->id,
            'book_title' => 'Pipeline Portfolio Book',
            'author_name' => 'Portfolio Author',
            'phone_numbers' => ['555-0101'],
            'assigned_to' => $agent->id,
            'assigned_date' => today(),
            'sales_stage' => 'pipeline',
        ]);
        Lead::create([
            'brand_id' => $brand->id,
            'book_title' => 'Another Agent Book',
            'author_name' => 'Another Author',
            'phone_numbers' => ['555-0102'],
            'assigned_to' => $otherAgent->id,
            'assigned_date' => today(),
        ]);

        $this->actingAs($agent)
            ->get(route('leads.assigned'))
            ->assertOk()
            ->assertSee('Pipeline Portfolio Book')
            ->assertDontSee('Another Agent Book')
            ->assertDontSee('Edit selected lead');

        $this->actingAs($agent)
            ->get(route('leads.new'))
            ->assertOk()
            ->assertDontSee('Pipeline Portfolio Book')
            ->assertDontSee('Another Agent Book');
    }
}
