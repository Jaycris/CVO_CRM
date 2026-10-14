<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Brand;
use App\Models\CommissionAdjustment;
use App\Models\CommissionProfile;
use App\Models\SalesActivity;
use App\Models\SalesEndorsement;
use App\Models\SalesPayment;
use App\Models\User;
use App\Models\Role;
use App\Support\CommissionBalances;
use App\Support\CommissionDisputes;
use App\Support\SalesActivitySync;
use App\Support\SalesMtdCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CommissionDisputesTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        // This unrelated rewards migration contains unconditional MySQL ALTER SQL.
        return [
            '--path' => array_values(array_filter(glob(database_path('migrations/*.php')), fn ($path) => basename($path) !== '2026_09_25_000003_add_monthly_period_to_reward_unlocks.php')),
            '--realpath' => true,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        AppSetting::set('commission_exchange_rate', 56);
        AppSetting::set('card_payment_hold_percent', 0);
        $this->travelTo(Carbon::parse('2026-10-15 12:00:00'));
    }

    public function test_dispute_preserves_original_sale_and_reverses_only_its_commission_once(): void
    {
        [$payment, $agent] = $this->sale(2000, '2026-09-10');
        $original = $this->rows('2026-09', $agent)->sum('usd_total');
        $this->assertEquals(300, $original);

        $this->transition($payment, 'Dispute');
        $this->transition($payment, 'Dispute');

        $this->assertDatabaseCount('commission_adjustments', 1);
        $this->assertEquals(-300, CommissionAdjustment::first()->usd_amount);
        $this->assertEquals(2000, $this->rows('2026-09', $agent)->sum('amount'));
        $this->assertEquals($original, $this->rows('2026-09', $agent)->sum('usd_total'));
        $this->assertEquals(-300, $this->rows('2026-10', $agent)->sum('usd_total'));
        $this->assertEquals(-16800, $this->rows('2026-10', $agent)->sum('net_commission'));
    }

    public function test_debt_survives_empty_months_and_new_commissions_cover_it_only_once(): void
    {
        [$payment, $agent] = $this->sale(2000, '2026-09-10');
        $this->transition($payment, 'Dispute');
        $this->sale(1000, '2026-11-10', $agent);
        $this->sale(2000, '2026-12-10', $agent);

        $this->assertEquals(-300, $this->rows('2026-10', $agent)->sum('usd_total'));
        $this->assertEquals(-150, $this->rows('2026-11', $agent)->sum('usd_total'));
        $this->assertEquals(150, $this->rows('2026-12', $agent)->sum('usd_total'));
        $this->assertEquals(0, $this->rows('2027-01', $agent)->sum('usd_total'));
        $this->assertEquals(-300, $this->rows('2026-10', $agent)->sum('usd_total'));
    }

    public function test_restoration_offsets_the_deduction_without_rewriting_earlier_months(): void
    {
        [$payment, $agent] = $this->sale(2000, '2026-09-10');
        $this->transition($payment, 'Dispute');
        $this->travelTo(Carbon::parse('2026-11-15 12:00:00'));
        $this->transition($payment, 'Payment Success');
        $this->transition($payment, 'Payment Success');

        $this->assertDatabaseCount('commission_adjustments', 2);
        $this->assertEquals(-300, $this->rows('2026-10', $agent)->sum('usd_total'));
        $this->assertEquals(0, $this->rows('2026-11', $agent)->sum('usd_total'));
        $this->assertEquals(300, $this->rows('2026-09', $agent)->sum('usd_total'));
    }

    public function test_adjustments_are_scoped_to_the_agent_and_brand(): void
    {
        [$payment, $agent] = $this->sale(2000, '2026-09-10');
        [, $other] = $this->sale(1000, '2026-10-10');
        $this->transition($payment, 'Dispute');

        $this->assertEquals(150, $this->rows('2026-10', $other)->sum('usd_total'));
        $this->assertCount(0, CommissionBalances::apply(collect(), Carbon::parse('2026-10-01'), null, $other->brand_id));
    }

    public function test_snapshot_stays_fixed_when_commission_settings_change(): void
    {
        [$payment, $agent] = $this->sale(2000, '2026-09-10');
        $this->transition($payment, 'Dispute');
        $agent->update(['is_commission_threshold_exempt' => false, 'commission_threshold_amount' => 2000]);
        $this->assertEquals(300, $this->rows('2026-09', $agent)->sum('usd_total'));
        $this->assertEquals(-300, $this->rows('2026-10', $agent)->sum('usd_total'));
    }

    public function test_two_thousand_sale_with_one_thousand_commission_deducts_one_thousand(): void
    {
        [$payment, $agent] = $this->sale(2000, '2026-09-10');
        $profile = CommissionProfile::create(['name' => 'Fifty percent']);
        $profile->rules()->create(['minimum_mtd_percent' => 0, 'commission_percent' => 50]);
        $agent->update(['commission_profile_id' => $profile->id]);

        $this->assertEquals(1000, $this->rows('2026-09', $agent)->sum('usd_total'));
        $this->transition($payment, 'Dispute');
        $this->assertEquals(-1000, $this->rows('2026-10', $agent)->sum('usd_total'));
        $this->assertEquals(-1000, $this->rows('2026-11', $agent)->sum('usd_total'));
    }

    public function test_zero_commission_creates_no_debt_and_shared_sales_reverse_each_share(): void
    {
        [$payment, $agent] = $this->sale(2000, '2026-09-10');
        $agent->update(['is_commission_threshold_exempt' => false, 'commission_threshold_amount' => 2000]);
        $this->transition($payment, 'Dispute');
        $this->assertEquals(0, $this->rows('2026-10', $agent)->sum('usd_total'));

        [$shared, $main] = $this->sale(2000, '2026-09-12');
        $frankie = User::factory()->create(['brand_id' => $main->brand_id, 'is_commission_threshold_exempt' => true]);
        $shared->endorsement->update(['has_frankie' => true, 'frankie_agent_id' => $frankie->id, 'frankie_commission_percent' => 50]);
        $shared->unsetRelation('endorsement');
        SalesActivitySync::sync($shared);
        $this->transition($shared, 'Dispute');
        $this->assertEquals(-150, $this->rows('2026-10', $main)->sum('usd_total'));
        $this->assertEquals(-150, $this->rows('2026-10', $frankie)->sum('usd_total'));
    }

    public function test_finance_payment_update_records_the_adjustment_and_protects_original_payment_values(): void
    {
        [$payment, $agent] = $this->sale(2000, '2026-09-10');
        $role = Role::firstOrCreate(['name' => 'Admin'], ['slug' => 'admin', 'department' => 'Admin']);
        $admin = User::factory()->create(['role_id' => $role->id, 'brand_id' => $agent->brand_id]);
        $payload = ['amount' => 2000, 'payment_method' => 'Wire Payment', 'sold_date' => '2026-09-10', 'status' => 'Dispute'];

        $this->actingAs($admin)->put(route('finance.payments.update', $payment), $payload)
            ->assertSessionHasNoErrors()->assertRedirect(route('finance.payments.index'));
        $this->assertEquals('Dispute', $payment->fresh()->status);
        $this->assertDatabaseCount('commission_adjustments', 1);

        $this->put(route('finance.payments.update', $payment), array_replace($payload, ['amount' => 1000]))
            ->assertSessionHasErrors('status');
        $this->assertEquals(2000, $payment->fresh()->amount);
        $this->assertDatabaseCount('commission_adjustments', 1);
    }

    public function test_legacy_disputes_can_be_previewed_then_reconciled_once_in_the_current_month(): void
    {
        [$first, $agent] = $this->sale(1500, '2026-09-29');
        [$second] = $this->sale(2999, '2026-09-29', $agent);
        $agent->update(['is_commission_threshold_exempt' => false, 'commission_threshold_amount' => 500]);
        $originalCommission = $this->rows('2026-09', $agent)->sum('usd_total');

        foreach ([$first, $second] as $payment) {
            $payment->update(['status' => 'Dispute']);
            SalesActivitySync::sync($payment);
        }
        $this->assertDatabaseCount('sales_activities', 0);

        $this->artisan('commissions:reconcile-disputes', ['--agent' => $agent->id])
            ->expectsOutput('Preview only. No changes saved. Run again with --apply to record these deductions.')
            ->assertSuccessful();
        $this->assertDatabaseCount('sales_activities', 0);
        $this->assertDatabaseCount('commission_adjustments', 0);

        $this->artisan('commissions:reconcile-disputes', ['--agent' => $agent->id, '--apply' => true])->assertSuccessful();
        $this->assertDatabaseCount('commission_adjustments', 2);
        $this->assertDatabaseCount('sales_activities', 2);
        $this->assertEquals('Dispute', $first->fresh()->status);
        $this->assertEquals('Dispute', $second->fresh()->status);
        $this->assertEquals($originalCommission, $this->rows('2026-09', $agent)->sum('usd_total'));
        $this->assertEquals(-$originalCommission, $this->rows('2026-10', $agent)->sum('usd_total'));
        $this->assertEquals(-$originalCommission, $this->rows('2026-11', $agent)->sum('usd_total'));

        $this->artisan('commissions:reconcile-disputes', ['--apply' => true])
            ->expectsOutput('No disputed payments with missing adjustments were found.')->assertSuccessful();
        $this->assertDatabaseCount('commission_adjustments', 2);
    }

    private function transition(SalesPayment $payment, string $status): void
    {
        DB::transaction(function () use ($payment, $status) {
            $locked = SalesPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            CommissionDisputes::transition($locked, $status);
            $locked->update(['status' => $status]);
            SalesActivitySync::sync($locked);
        });
    }

    private function sale(float $amount, string $date, ?User $agent = null): array
    {
        if (! $agent) {
            $brand = Brand::create(['imprint_name' => 'Commission Test ' . uniqid()]);
            $agent = User::factory()->create([
                'brand_id' => $brand->id, 'department' => 'Sales',
                'is_commission_eligible' => true, 'is_commission_threshold_exempt' => true,
            ]);
        }
        $endorsement = SalesEndorsement::create([
            'brand_id' => $agent->brand_id, 'agent_id' => $agent->id,
            'author_name' => 'Test Author', 'contact_number' => '5550100', 'email' => 'author@example.com',
            'street_name' => 'Test Street', 'city_state' => 'Test City', 'zip_code' => '1000',
            'book_title' => 'Test Book', 'isbn' => '123', 'services' => 'Test Service',
            'amount' => $amount, 'amount_to_be_paid' => $amount, 'payment' => 'Full Payment', 'remarks' => '',
        ]);
        $payment = SalesPayment::create([
            'brand_id' => $agent->brand_id, 'sales_endorsement_id' => $endorsement->id,
            'amount' => $amount, 'payment_method' => 'Wire Payment', 'sold_date' => $date, 'status' => 'Payment Success',
        ]);
        SalesActivitySync::sync($payment);

        return [$payment, $agent];
    }

    private function rows(string $month, User $agent)
    {
        $start = Carbon::parse($month . '-01');
        $activities = SalesActivity::with(['agent', 'frankieAgent', 'service'])
            ->whereBetween('sold_date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()])
            ->where('agent_id', $agent->id)->get();

        return CommissionBalances::apply(SalesMtdCalculator::statementRows($activities), $start, null, null, $agent->id);
    }
}
