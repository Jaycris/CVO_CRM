<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CommissionAdjustment;
use App\Models\CommissionDisputeOffset;
use App\Models\CommissionRelease;
use App\Models\Role;
use App\Models\SalesActivity;
use App\Models\SalesEndorsement;
use App\Models\SalesPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrashPaymentDeletionTest extends TestCase
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
        $brand = Brand::create(['imprint_name' => 'Trash Test Brand', 'is_sales_brand' => true]);
        $role = Role::firstOrCreate(['name' => 'Admin'], ['slug' => 'admin', 'department' => 'Admin']);
        $this->admin = User::factory()->create(['role_id' => $role->id, 'brand_id' => $brand->id]);
        $this->agent = User::factory()->create(['brand_id' => $brand->id, 'department' => 'Sales']);
        $this->actingAs($this->admin);
    }

    private function payment(string $status = 'Refund'): SalesPayment
    {
        $endorsement = SalesEndorsement::create(['brand_id' => $this->agent->brand_id, 'agent_id' => $this->agent->id,
            'author_name' => 'Pat Mann', 'book_title' => 'Trash Test Book', 'contact_number' => '5550100',
            'street_name' => 'Street', 'city_state' => 'City', 'zip_code' => '1000', 'isbn' => '123',
            'services' => 'Publishing', 'amount' => 2000, 'amount_to_be_paid' => 2000, 'payment' => 'Full Payment']);
        $payment = SalesPayment::create(['brand_id' => $this->agent->brand_id, 'sales_endorsement_id' => $endorsement->id,
            'amount' => 2000, 'payment_method' => 'Card', 'sold_date' => '2026-10-28', 'status' => $status]);
        $payment->delete();
        return $payment;
    }

    private function release(SalesPayment $payment): CommissionRelease
    {
        return CommissionRelease::create(['sales_payment_id' => $payment->id, 'agent_id' => $this->agent->id,
            'brand_id' => $this->agent->brand_id, 'status' => 'unreleased', 'source' => 'phrems',
            'reason' => 'Fetched release status', 'created_by' => $this->admin->id]);
    }

    private function deleteFromTrash(array $ids, string $type = 'payments')
    {
        return $this->from(route('admin.trash.index', ['type' => $type]))->delete(route('admin.trash.force-delete'), [
            'type' => $type, 'record_ids' => $ids,
        ]);
    }

    public function test_refund_with_release_history_redirects_with_a_specific_message_instead_of_500(): void
    {
        $payment = $this->payment();
        $release = $this->release($payment);
        $before = $release->fresh()->getAttributes();
        $this->deleteFromTrash([$payment->id])->assertRedirect(route('admin.trash.index', ['type' => 'payments']))
            ->assertSessionHasErrors('deletion')->assertSessionMissing('success');
        $this->assertSoftDeleted($payment);
        $this->assertSame($before, $release->fresh()->getAttributes());
        $this->get(route('admin.trash.index', ['type' => 'payments']))->assertOk()
            ->assertSee('linked commission release, dispute, or offset history')->assertSee('No selected records were deleted.')
            ->assertDontSee('Please select at least one record.');
    }

    public function test_two_record_batch_is_atomic_when_one_payment_has_protected_history(): void
    {
        $ordinary = $this->payment();
        $protected = $this->payment();
        $this->release($protected);
        $this->deleteFromTrash([$ordinary->id, $protected->id])->assertSessionHasErrors('deletion');
        $this->assertSoftDeleted($ordinary);
        $this->assertSoftDeleted($protected);
    }

    public function test_refunds_without_commission_history_can_still_be_permanently_deleted(): void
    {
        $first = $this->payment();
        $second = $this->payment();
        $this->deleteFromTrash([$first->id, $second->id])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertDatabaseMissing('sales_payments', ['id' => $first->id]);
        $this->assertDatabaseMissing('sales_payments', ['id' => $second->id]);
        $this->assertDatabaseHas('sales_endorsements', ['id' => $first->sales_endorsement_id]);
    }

    public function test_funding_payment_and_its_ledger_history_are_not_erased(): void
    {
        $funding = $this->payment();
        $source = $this->payment('Dispute');
        $release = $this->release($funding);
        $entry = CommissionAdjustment::create(['sales_payment_id' => $source->id, 'agent_id' => $this->agent->id,
            'brand_id' => $this->agent->brand_id, 'kind' => 'dispute', 'effective_date' => '2026-11-06',
            'usd_amount' => -500.25, 'settlement_basis' => 'released', 'original_commission' => ['usd_total' => 500.25]]);
        $offset = CommissionDisputeOffset::create(['commission_adjustment_id' => $entry->id, 'sales_payment_id' => $funding->id,
            'agent_id' => $this->agent->id, 'commission_release_id' => $release->id, 'commission_date' => '2026-10-28',
            'dispute_date' => '2026-11-06', 'usd_amount' => 500.25, 'commission_snapshot' => ['usd_total' => 720]]);
        $this->deleteFromTrash([$funding->id, $source->id])->assertSessionHasErrors('deletion');
        $this->assertSoftDeleted($funding);
        $this->assertSoftDeleted($source);
        $this->assertEquals(500.25, $offset->fresh()->usd_amount);
        $this->assertEquals(-500.25, $entry->fresh()->usd_amount);
    }

    public function test_endorsement_cascade_cannot_delete_a_linked_payment_even_when_the_payment_is_active(): void
    {
        $payment = $this->payment();
        $payment->restore();
        $this->release($payment);
        $endorsement = $payment->endorsement;
        $endorsement->delete();
        $payment->fresh()->restore();
        $this->deleteFromTrash([$endorsement->id], 'endorsements')->assertSessionHasErrors('deletion');
        $this->assertSoftDeleted($endorsement);
        $this->assertDatabaseHas('sales_payments', ['id' => $payment->id, 'deleted_at' => null]);
    }

    public function test_other_foreign_key_restrictions_are_reported_and_the_entire_batch_rolls_back(): void
    {
        $payment = $this->payment();
        $release = $this->release($payment);
        $ordinary = User::factory()->create();
        $protected = User::factory()->create();
        $release->update(['agent_id' => $protected->id]);
        $ordinary->delete();
        $protected->delete();
        $this->deleteFromTrash([$ordinary->id, $protected->id], 'users')->assertSessionHasErrors('deletion');
        $this->assertSoftDeleted($ordinary);
        $this->assertSoftDeleted($protected);
    }

    public function test_only_admin_can_permanently_delete_and_live_payments_are_not_deleted(): void
    {
        $payment = $this->payment();
        $this->actingAs($this->agent);
        $this->deleteFromTrash([$payment->id])->assertForbidden();
        $this->assertSoftDeleted($payment);
        $this->actingAs($this->admin);
        $payment->restore();
        $this->deleteFromTrash([$payment->id])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('sales_payments', ['id' => $payment->id, 'deleted_at' => null]);
        $this->deleteFromTrash([])->assertSessionHasErrors('record_ids');
    }

    public function test_protected_payment_can_still_be_restored_with_its_release_history_intact(): void
    {
        $payment = $this->payment();
        $release = $this->release($payment);
        $before = $release->fresh()->getAttributes();
        $this->post(route('admin.trash.restore'), ['type' => 'payments', 'record_ids' => [$payment->id]])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertDatabaseHas('sales_payments', ['id' => $payment->id, 'deleted_at' => null]);
        $this->assertSame($before, $release->fresh()->getAttributes());
    }

    public function test_frozen_commission_snapshot_is_protected_even_without_a_release_marker(): void
    {
        $payment = $this->payment();
        $activity = SalesActivity::create(['sales_payment_id' => $payment->id, 'brand_id' => $this->agent->brand_id,
            'agent_id' => $this->agent->id, 'activity_type' => 'payment_success', 'amount' => 2000,
            'agent_credit_amount' => 2000, 'payment_status' => 'Payment Success', 'sold_date' => '2026-10-28',
            'commission_snapshot' => [$this->agent->id => ['usd_total' => 500.25]]]);
        $this->deleteFromTrash([$payment->id])->assertSessionHasErrors('deletion');
        $this->assertSoftDeleted($payment);
        $this->assertEquals(500.25, $activity->fresh()->commission_snapshot[$this->agent->id]['usd_total']);
    }
}
