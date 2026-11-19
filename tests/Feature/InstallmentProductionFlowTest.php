<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Permission;
use App\Models\ProductionProject;
use App\Models\Role;
use App\Models\SalesEndorsement;
use App\Models\SalesPayment;
use App\Models\User;
use App\Notifications\ProductionProjectsEndorsedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InstallmentProductionFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $officer;

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
        Notification::fake();
        $brand = Brand::create(['imprint_name' => 'Installment Test Brand']);
        $adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin', 'department' => 'Admin']);
        $this->admin = User::factory()->create(['brand_id' => $brand->id, 'role_id' => $adminRole->id]);
        $officerRole = Role::create(['name' => 'Fulfillment Officer', 'slug' => 'fulfillment-officer', 'department' => 'Production']);
        $permission = Permission::firstOrCreate(['key' => 'view_all_fulfillment_trackers'], ['group' => 'Production', 'label' => 'View All Fulfillment Trackers']);
        $officerRole->permissionRecords()->attach($permission);
        $this->officer = User::factory()->create([
            'brand_id' => $brand->id, 'role_id' => $officerRole->id, 'department' => 'Production',
        ]);
        $this->actingAs($this->admin);
    }

    private function endorsement(): SalesEndorsement
    {
        return SalesEndorsement::create([
            'brand_id' => $this->admin->brand_id, 'agent_id' => $this->admin->id,
            'author_name' => 'Installment Author', 'contact_number' => '5550100',
            'street_name' => 'Test Street', 'city_state' => 'Test City', 'zip_code' => '1000',
            'book_title' => 'Installment Book', 'isbn' => '123', 'services' => 'Publishing Package',
            'amount' => 1500, 'amount_to_be_paid' => 1500, 'payment' => 'Installment Payment',
            'contract_status' => 'signed',
        ]);
    }

    private function endorse(array $endorsements)
    {
        return $this->post(route('finance.contracts.endorse-production'), [
            'endorsement_ids' => array_map(fn ($endorsement) => $endorsement->id, $endorsements),
            'tracker_type' => 'publishing', 'endorsement_notes' => 'New endorsement',
        ]);
    }

    public function test_later_installments_keep_one_project_and_do_not_reset_existing_work(): void
    {
        $endorsement = $this->endorsement();
        SalesPayment::create([
            'brand_id' => $endorsement->brand_id, 'sales_endorsement_id' => $endorsement->id,
            'amount' => 500, 'payment_method' => 'Card', 'sold_date' => '2026-09-20', 'status' => 'Payment Success',
        ]);
        $this->endorse([$endorsement])->assertSessionHasNoErrors();
        $project = ProductionProject::firstOrFail();
        $project->update([
            'status' => 'in_progress', 'tracker_type' => 'marketing', 'started_at' => now()->subDays(10),
            'assigned_to' => $this->officer->id, 'endorsement_notes' => 'Original instructions',
        ]);
        $task = $project->tasks()->create([
            'assigned_to' => $this->officer->id, 'title' => 'Existing work', 'status' => 'in_progress', 'progress' => 60,
        ]);
        $beforeProject = $project->fresh()->getAttributes();
        $beforeTask = $task->fresh()->getAttributes();
        Notification::fake();

        foreach (['2026-10-20', '2026-11-20'] as $date) {
            $this->post(route('finance.payments.store'), [
                'sales_endorsement_id' => $endorsement->id, 'amount' => 500,
                'payment_method' => 'Card', 'sold_date' => $date, 'status' => 'Payment Success',
            ])->assertSessionHasNoErrors();
            $this->endorse([$endorsement])->assertSessionHasNoErrors()->assertSessionHas('success');
        }

        $this->assertDatabaseCount('production_projects', 1);
        $this->assertDatabaseCount('sales_payments', 3);
        $this->assertEquals(1500, $endorsement->paymentRecords()->sum('amount'));
        $this->assertSame($beforeProject, $project->fresh()->getAttributes());
        $this->assertSame($beforeTask, $task->fresh()->getAttributes());
        Notification::assertNotSentTo($this->officer, ProductionProjectsEndorsedNotification::class);
    }

    public function test_repeated_endorsement_does_not_restart_a_fulfilled_project(): void
    {
        $endorsement = $this->endorsement();
        $this->endorse([$endorsement])->assertSessionHasNoErrors();
        $project = ProductionProject::firstOrFail();
        $project->update(['status' => 'fulfilled', 'completed_at' => now(), 'notes' => 'Delivered']);
        $before = $project->fresh()->getAttributes();

        $this->endorse([$endorsement])->assertSessionHas('success');
        $this->assertDatabaseCount('production_projects', 1);
        $this->assertSame($before, $project->fresh()->getAttributes());
    }

    public function test_archived_projects_are_not_automatically_restored_by_installment_endorsements(): void
    {
        $endorsement = $this->endorsement();
        $this->endorse([$endorsement]);
        $project = ProductionProject::firstOrFail();
        $project->delete();

        $this->endorse([$endorsement])->assertSessionHas('success');
        $this->assertSame(1, ProductionProject::withTrashed()->count());
        $this->assertSame(0, ProductionProject::count());
    }

    public function test_batch_endorsement_creates_and_notifies_only_for_new_contracts(): void
    {
        $existing = $this->endorsement();
        $new = $this->endorsement();
        $this->endorse([$existing]);
        Notification::fake();

        $this->endorse([$existing, $new])->assertSessionHasNoErrors()->assertSessionHas('success',
            '1 project(s) endorsed to Production successfully. 1 already-linked project(s) left unchanged.');
        $this->assertDatabaseCount('production_projects', 2);
        Notification::assertSentTo($this->officer, ProductionProjectsEndorsedNotification::class,
            fn ($notification) => $notification->toArray($this->officer)['title'] === '1 New Project for Publishing');
        Notification::assertSentToTimes($this->officer, ProductionProjectsEndorsedNotification::class, 1);
    }

    public function test_project_details_survive_trashed_endorsement_payments_and_agent_without_restoring_or_resetting_work(): void
    {
        $agent = User::factory()->create(['brand_id' => $this->admin->brand_id, 'first_name' => 'Historical', 'last_name' => 'Agent']);
        $endorsement = $this->endorsement();
        $endorsement->update(['agent_id' => $agent->id]);
        $payment = SalesPayment::create(['brand_id' => $endorsement->brand_id, 'sales_endorsement_id' => $endorsement->id,
            'amount' => 500, 'payment_method' => 'Card', 'sold_date' => '2026-10-28', 'status' => 'Payment Success']);
        $this->endorse([$endorsement])->assertSessionHasNoErrors();
        $project = ProductionProject::firstOrFail();
        $project->update(['status' => 'in_progress', 'assigned_to' => $this->officer->id, 'fulfillment_officer_id' => $this->officer->id]);
        $task = $project->tasks()->create(['assigned_to' => $this->officer->id, 'title' => 'Existing production work', 'status' => 'in_progress', 'progress' => 90]);
        $beforeProject = $project->fresh()->getAttributes();
        $beforeTask = $task->fresh()->getAttributes();
        $endorsement->delete();
        $agent->delete();

        $response = $this->get(route('production.projects.index'))->assertOk()
            ->assertSee('Installment Author')->assertSee('Installment Book')->assertSee('Publishing Package')
            ->assertSee('Historical Agent')->assertSee('Sales endorsement in Trash')->assertSee('Oct 28, 2026')->assertSee('90%');
        $row = $response->viewData('projects')->firstWhere('id', $project->id);
        $this->assertNotNull($row->endorsement);
        $this->assertTrue($row->endorsement->trashed());
        $this->assertTrue($row->endorsement->agent->trashed());
        $this->assertEquals($payment->id, $row->endorsement->latestHistoricalPayment->id);
        $this->assertSoftDeleted($endorsement);
        $this->assertSoftDeleted($payment);
        $this->assertSoftDeleted($agent);
        $this->assertSame($beforeProject, $project->fresh()->getAttributes());
        $this->assertSame($beforeTask, $task->fresh()->getAttributes());
        $this->assertSame(90, $project->fresh()->progress_percentage);
        $this->get(route('production.projects.index', ['search' => 'Historical']))->assertOk()->assertSee('Installment Author');
    }

    public function test_historical_payment_date_fallback_does_not_turn_a_refund_into_successful_payment(): void
    {
        $endorsement = $this->endorsement();
        $this->endorse([$endorsement])->assertSessionHasNoErrors();
        $payment = SalesPayment::create(['brand_id' => $endorsement->brand_id, 'sales_endorsement_id' => $endorsement->id,
            'amount' => 500, 'payment_method' => 'Card', 'sold_date' => '2026-10-28', 'status' => 'Refund']);
        $payment->delete();
        $this->get(route('production.projects.index'))->assertOk()->assertSee('Oct 28, 2026')->assertSee('Installment Author');
        $this->assertNull($endorsement->fresh()->successfulPaymentRecord);
        $this->assertEquals($payment->id, $endorsement->fresh()->latestHistoricalPayment->id);
        $this->assertSame('Refund', $payment->fresh()->status);
        $this->assertSoftDeleted($payment);
    }

    public function test_users_without_permission_cannot_endorse_projects(): void
    {
        $endorsement = $this->endorsement();
        $this->actingAs(User::factory()->create(['brand_id' => $endorsement->brand_id]));
        $this->endorse([$endorsement])->assertForbidden();
        $this->assertDatabaseCount('production_projects', 0);
    }
}
