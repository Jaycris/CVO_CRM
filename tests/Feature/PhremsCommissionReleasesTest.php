<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Brand;
use App\Models\CommissionAdjustment;
use App\Models\CommissionAdjustmentReconciliation;
use App\Models\CommissionDisputeOffset;
use App\Models\CommissionRelease;
use App\Models\Role;
use App\Models\SalesActivity;
use App\Models\SalesEndorsement;
use App\Models\SalesPayment;
use App\Models\Service;
use App\Models\User;
use App\Support\CommissionDisputes;
use App\Support\PhremsCommissionReleases;
use App\Support\SalesActivitySync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PhremsCommissionReleasesTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;
    private User $admin;

    protected function migrateFreshUsing(): array
    {
        return ['--path' => array_values(array_filter(glob(database_path('migrations/*.php')), fn ($path) => basename($path) !== '2026_09_25_000003_add_monthly_period_to_reward_unlocks.php')), '--realpath' => true];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-06 12:00:00'));
        Http::preventStrayRequests();
        config(['services.hris.commission_release_token' => 'dedicated-release-token']);
        AppSetting::set('hris_base_url', 'https://phrems.test');
        AppSetting::set('commission_exchange_rate', 61);
        AppSetting::set('card_payment_hold_percent', 15);
        $brand = Brand::create(['imprint_name' => 'Release Test Brand', 'is_sales_brand' => true]);
        $role = Role::firstOrCreate(['name' => 'Admin'], ['slug' => 'admin', 'department' => 'Admin']);
        $this->admin = User::factory()->create(['role_id' => $role->id, 'brand_id' => $brand->id, 'department' => 'Admin']);
        $this->agent = User::factory()->create(['brand_id' => $brand->id, 'department' => 'Sales', 'is_commission_eligible' => true,
            'is_commission_threshold_exempt' => true, 'markup_commission_percent' => 50, 'hris_employee_id' => 'EMP-RELEASE']);
        $this->actingAs($this->admin);
    }

    private function sale(string $date = '2026-10-10'): SalesPayment
    {
        $service = Service::firstOrCreate(['brand_id' => $this->agent->brand_id, 'name' => 'Release Service'], ['category' => 'Marketing', 'price' => 1000]);
        $endorsement = SalesEndorsement::create([
            'brand_id' => $this->agent->brand_id, 'agent_id' => $this->agent->id, 'author_name' => 'Release Author '.$date,
            'contact_number' => '5550100', 'street_name' => 'Street', 'city_state' => 'City', 'zip_code' => '1000',
            'book_title' => 'Release Book '.$date, 'isbn' => '123', 'service_id' => $service->id, 'services' => $service->name,
            'amount' => 2000, 'amount_to_be_paid' => 2000, 'payment' => 'Full Payment',
        ]);
        $payment = SalesPayment::create(['brand_id' => $this->agent->brand_id, 'sales_endorsement_id' => $endorsement->id,
            'amount' => 2000, 'payment_method' => 'Card', 'sold_date' => $date, 'status' => 'Payment Success']);
        SalesActivitySync::sync($payment);
        return $payment;
    }

    private function payload(SalesPayment $payment, string $status = 'released'): array
    {
        $result = ['key' => $payment->id.':'.$this->agent->id, 'status' => $status];
        if ($status === 'released') {
            $result += ['reference' => 'slip:42/line:17', 'released_at' => '2026-10-30T08:00:00Z', 'breakdown' => [
                'usd_total' => 600, 'service_commission' => 100, 'markup_commission' => 500,
                'php_total' => 36000, 'hold_amount' => 5400, 'net_commission' => 30600,
                'exchange_rate' => 60, 'card_payment_hold_percent' => 15,
            ]];
        }
        return ['complete' => true, 'hris_employee_id' => 'EMP-RELEASE', 'month' => '2026-10', 'release_basis' => 'sent', 'data' => [$result]];
    }

    private function dispute(SalesPayment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $payment = SalesPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            CommissionDisputes::transition($payment, 'Dispute');
            $payment->update(['status' => 'Dispute']);
            SalesActivitySync::sync($payment);
        });
    }

    private function makeLegacyDeduction(SalesPayment $payment): CommissionAdjustment
    {
        $this->dispute($payment);
        $entry = CommissionAdjustment::firstOrFail();
        $original = $entry->original_commission;
        unset($original['settlement_breakdown']);
        $entry->update(['settlement_basis' => null, 'usd_amount' => -450.30, 'original_commission' => $original]);
        CommissionRelease::query()->delete();
        return $entry->fresh();
    }

    private function fakeStatuses(array $statuses): void
    {
        Http::fake(function (Request $request) use ($statuses) {
            $data = [];
            foreach ($request['items'] as $item) {
                [$status, $usd] = $statuses[$item['crm_payment_id']] ?? ['unknown', 0];
                $result = ['key' => $item['key'], 'status' => $status];
                if ($status === 'released') $result += [
                    'reference' => 'slip:41/line:'.$item['crm_payment_id'], 'released_at' => '2026-10-30T08:00:00Z',
                    'breakdown' => ['usd_total' => $usd, 'service_commission' => $usd, 'markup_commission' => 0,
                        'php_total' => $usd * 61, 'hold_amount' => round($usd * 61 * .15, 2),
                        'net_commission' => round($usd * 61 * .85, 2), 'exchange_rate' => 61, 'card_payment_hold_percent' => 15],
                ];
                $data[] = $result;
            }
            return Http::response(['complete' => true, 'hris_employee_id' => $request['hris_employee_id'],
                'month' => $request['month'], 'release_basis' => 'sent', 'data' => $data]);
        });
    }

    private function summary(string $month, array $extra = []): array
    {
        AppSetting::set('hris_api_token', 'offset-api-test');
        return $this->withToken('offset-api-test')->getJson(route('api.hris.commission-slip', $extra + [
            'month' => $month, 'hris_employee_id' => 'EMP-RELEASE',
        ]))->assertOk()->json('summary');
    }

    public function test_dispute_automatically_uses_older_unpaid_commission_and_clears_current_debt_once(): void
    {
        $source = $this->sale('2026-10-16');
        $pending = $this->sale('2026-10-28');
        $this->fakeStatuses([$source->id => ['released', 500.25], $pending->id => ['unreleased', 0]]);
        $this->dispute($source);
        $this->assertDatabaseCount('commission_dispute_offsets', 1);
        $offset = CommissionDisputeOffset::firstOrFail();
        $this->assertEquals(500.25, $offset->usd_amount);
        $this->assertEquals($pending->id, $offset->sales_payment_id);
        $this->assertEquals(-500.25, CommissionAdjustment::first()->usd_amount);
        $this->assertEquals(0, $this->summary('2026-11')['usd_total']);
        $this->assertEquals(0, $this->summary('2026-11')['net_commission']);
        $this->assertEquals(0, $this->summary('2026-12')['usd_total']);
        $run = $this->summary('2026-10', ['from' => '2026-10-25', 'to' => '2026-10-31']);
        $this->assertEquals(149.75, $run['usd_total']);
        $this->assertEquals(3187.25, $run['net_commission']);
        // Production guards discard non-column constructor attributes. Labels must still survive.
        $wasUnguarded = \Illuminate\Database\Eloquent\Model::isUnguarded();
        \Illuminate\Database\Eloquent\Model::reguard();
        try {
            $this->get(route('reports.agent-statements.index', ['month' => '2026-10', 'agent_id' => $this->agent->id]))
                ->assertOk()->assertSee('Commission applied to prior dispute')->assertDontSee('Commission adjustment deduction');
            $this->get(route('reports.agent-statements.index', ['month' => '2026-11', 'agent_id' => $this->agent->id]))
                ->assertOk()->assertSee('Dispute covered by prior commission');
        } finally {
            \Illuminate\Database\Eloquent\Model::unguard($wasUnguarded);
        }
        $releasedRun = $this->summary('2026-10', ['from' => '2026-10-01', 'to' => '2026-10-24']);
        $this->assertEquals(650, $releasedRun['usd_total']);
        $this->assertEquals(799.75, $this->summary('2026-10')['usd_total']);
        $this->post(route('reports.commission-releases.sync'), ['month' => '2026-10', 'agent_id' => $this->agent->id])->assertSessionHasNoErrors();
        $this->dispute($source);
        $this->assertDatabaseCount('commission_dispute_offsets', 1);
        $this->assertDatabaseCount('commission_adjustments', 1);
        $this->assertEquals(0, $this->summary('2026-11')['usd_total']);
        $this->get(route('reports.commission-releases.index', ['month' => '2026-10', 'agent_id' => $this->agent->id]))
            ->assertOk()->assertSee('Covered by earlier unpaid commissions: $500.25')->assertSee('Applied to dispute debt: $500.25');
        $this->put(route('finance.payments.update', $pending), ['amount' => 1900, 'payment_method' => 'Card', 'sold_date' => '2026-10-28', 'status' => 'Payment Success'])
            ->assertSessionHasErrors('status');
        $this->assertEquals(2000, $pending->fresh()->amount);
        $this->post(route('admin.sales-mtd-deductions.store'), ['request_key' => (string) \Illuminate\Support\Str::uuid(),
            'agent_id' => $this->agent->id, 'month' => '2026-10', 'amount' => 500, 'reason' => 'Attempt to change reserved commission'])
            ->assertSessionHasErrors('amount');
        $pending->endorsement->service->update(['price' => 1900]);
        $this->assertEquals(3187.25, $this->summary('2026-10', ['from' => '2026-10-25', 'to' => '2026-10-31'])['net_commission']);
    }

    public function test_later_fetch_does_not_collect_an_old_debt_again_after_subsequent_commissions_exist(): void
    {
        $source = $this->sale('2026-10-16');
        $this->fakeStatuses([$source->id => ['released', 500.25]]);
        $this->dispute($source);
        $pending = $this->sale('2026-10-28');
        $this->sale('2026-11-05');
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::preventStrayRequests();
        $this->fakeStatuses([$source->id => ['released', 500.25], $pending->id => ['unreleased', 0]]);
        $this->post(route('reports.commission-releases.sync'), ['month' => '2026-10', 'agent_id' => $this->agent->id])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('commission_dispute_offsets', 0);
    }

    public function test_existing_period_debt_reduces_the_unpaid_commission_available_for_a_new_offset(): void
    {
        $source = $this->sale('2026-10-16');
        $pending = $this->sale('2026-10-28');
        CommissionAdjustment::create(['agent_id' => $this->agent->id, 'brand_id' => $this->agent->brand_id,
            'kind' => 'dispute', 'effective_date' => '2026-10-20', 'settlement_basis' => 'released',
            'usd_amount' => -100, 'original_commission' => ['author_name' => 'Prior obligation']]);
        $this->fakeStatuses([$source->id => ['released', 500.25], $pending->id => ['unreleased', 0]]);
        $this->dispute($source);
        $this->assertEquals(452.50, CommissionDisputeOffset::sum('usd_amount'));
        $this->assertEquals(-47.75, $this->summary('2026-11')['usd_total']);
    }

    public function test_restoring_a_dispute_refunds_only_its_original_amount_after_the_unpaid_offset(): void
    {
        $source = $this->sale('2026-10-16');
        $pending = $this->sale('2026-10-28');
        $this->fakeStatuses([$source->id => ['released', 500.25], $pending->id => ['unreleased', 0]]);
        $this->dispute($source);
        $this->travelTo(Carbon::parse('2026-12-10 12:00:00'));
        DB::transaction(function () use ($source) {
            $locked = SalesPayment::whereKey($source->id)->lockForUpdate()->firstOrFail();
            CommissionDisputes::transition($locked, 'Payment Success');
            $locked->update(['status' => 'Payment Success']);
            SalesActivitySync::sync($locked);
        });
        $this->assertEquals(0, $this->summary('2026-11')['usd_total']);
        $this->assertEquals(500.25, $this->summary('2026-12')['usd_total']);
        $this->assertDatabaseCount('commission_dispute_offsets', 1);
        $this->assertDatabaseCount('commission_adjustments', 2);
    }

    public function test_offset_respects_payable_after_card_hold_and_carries_only_the_uncovered_remainder(): void
    {
        $source = $this->sale('2026-10-16');
        $pending = $this->sale('2026-10-28');
        $this->fakeStatuses([$source->id => ['released', 700], $pending->id => ['unreleased', 0]]);
        $this->dispute($source);
        $this->assertEquals(552.50, CommissionDisputeOffset::sum('usd_amount'));
        $this->assertEquals(-147.50, $this->summary('2026-11')['usd_total']);
        $this->assertEquals(-147.50, $this->summary('2026-12')['usd_total']);
        $this->assertEquals(0, $this->summary('2026-10', ['from' => '2026-10-28', 'to' => '2026-10-28'])['net_commission']);
    }

    public function test_unknown_or_released_commissions_never_fund_an_offset(): void
    {
        $source = $this->sale('2026-10-16');
        $other = $this->sale('2026-10-28');
        $this->fakeStatuses([$source->id => ['released', 500.25], $other->id => ['unknown', 0]]);
        $this->dispute($source);
        $this->assertDatabaseCount('commission_dispute_offsets', 0);
        $this->assertEquals(-500.25, $this->summary('2026-11')['usd_total']);
    }

    public function test_two_disputes_cannot_spend_the_same_unpaid_commission_twice(): void
    {
        $first = $this->sale('2026-10-10');
        $second = $this->sale('2026-10-16');
        $pending = $this->sale('2026-10-28');
        $this->fakeStatuses([$first->id => ['released', 500.25], $second->id => ['released', 500.25], $pending->id => ['unreleased', 0]]);
        $this->dispute($first);
        $this->dispute($second);
        $this->assertEquals(552.50, CommissionDisputeOffset::sum('usd_amount'));
        $this->assertDatabaseCount('commission_dispute_offsets', 2);
        $this->assertEquals(-448, $this->summary('2026-11')['usd_total']);
        $this->assertEquals(0, $this->summary('2026-10', ['from' => '2026-10-28', 'to' => '2026-10-28'])['net_commission']);
    }

    public function test_cancelling_the_unpaid_funding_commission_reinstates_only_the_uncovered_original_debt(): void
    {
        $source = $this->sale('2026-10-16');
        $pending = $this->sale('2026-10-28');
        $this->fakeStatuses([$source->id => ['released', 500.25], $pending->id => ['unreleased', 0]]);
        $this->dispute($source);
        $this->assertEquals(0, $this->summary('2026-11')['usd_total']);
        $this->dispute($pending);
        $this->assertNotNull(CommissionDisputeOffset::first()->voided_at);
        $this->assertEquals(-500.25, $this->summary('2026-11')['usd_total']);
        $this->assertEquals(-500.25, $this->summary('2026-12')['usd_total']);
        $this->assertEquals(650, $this->summary('2026-10')['usd_total']);
    }

    public function test_fetch_automatically_offsets_an_existing_verified_legacy_deduction(): void
    {
        $source = $this->sale('2026-10-16');
        $this->fakeStatuses([$source->id => ['released', 500.25]]);
        $entry = $this->makeLegacyDeduction($source);
        $entry->update(['usd_amount' => -500.25]);
        $pending = $this->sale('2026-10-28');
        // Replace the earlier fake callback with a fresh factory so both candidates are now visible.
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::preventStrayRequests();
        $this->fakeStatuses([$source->id => ['released', 500.25], $pending->id => ['unreleased', 0]]);
        $this->post(route('reports.commission-releases.sync'), ['month' => '2026-10', 'agent_id' => $this->agent->id])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('commission_dispute_offsets', 1);
        $this->assertDatabaseCount('commission_adjustments', 1);
        $this->assertEquals(0, $this->summary('2026-11')['usd_total']);
        $this->assertNull($entry->fresh()->settlement_basis);
    }

    public function test_admin_reconciles_legacy_dispute_in_place_using_phrems_amount_and_actual_date_once(): void
    {
        $payment = $this->sale('2026-10-16');
        $verified = $this->payload($payment);
        $verified['data'][0]['breakdown']['usd_total'] = 500.25;
        Http::fakeSequence()->push($this->payload($payment))->push($verified);
        $entry = $this->makeLegacyDeduction($payment);
        $snapshot = SalesActivity::first()->commission_snapshot;
        $this->travelTo(Carbon::parse('2026-12-10 12:00:00'));
        $params = ['disputed_at' => '2026-11-06', 'reason' => 'Match the original dispute to the confirmed PHREMS sent commission.'];
        $url = route('reports.commission-releases.reconcile', $entry->id);
        $page = route('reports.commission-releases.index', ['month' => '2026-10', 'agent_id' => $this->agent->id]);
        // A fresh released lookup is mandatory even if the page did not previously fetch a status.
        $this->post($url, $params)->assertSessionHasNoErrors();
        $this->post($url, $params)->assertSessionHasNoErrors()->assertSessionHas('success', fn ($message) => str_contains($message, 'already reconciled'));
        $this->assertDatabaseCount('commission_adjustments', 1);
        $this->assertDatabaseCount('commission_adjustment_reconciliations', 1);
        $this->assertSame('Dispute', $payment->fresh()->status);
        $this->assertSame($snapshot, SalesActivity::first()->commission_snapshot);
        $this->assertEquals(-500.25, $entry->fresh()->usd_amount);
        $this->assertSame('2026-11-06', $entry->fresh()->effective_date->toDateString());
        $this->assertSame('released', $entry->fresh()->settlement_basis);
        $audit = CommissionAdjustmentReconciliation::firstOrFail();
        $this->assertEquals(-450.30, $audit->before_state['usd_amount']);
        $this->assertEquals(-500.25, $audit->after_state['usd_amount']);
        $this->assertEquals($this->admin->id, $audit->created_by);
        $this->assertEquals(CommissionRelease::first()->id, $audit->commission_release_id);
        $this->get($page)->assertOk()->assertSee('Reconciliation audit')->assertSee('500.25')->assertSee('450.30')->assertDontSee('Apply reconciliation');
        AppSetting::set('hris_api_token', 'reconcile-api-test');
        $slip = fn ($month) => $this->withToken('reconcile-api-test')->getJson(route('api.hris.commission-slip', [
            'month' => $month, 'hris_employee_id' => 'EMP-RELEASE',
        ]))->assertOk()->json('summary');
        $this->assertEquals(650, $slip('2026-10')['usd_total']);
        $this->assertEquals(-500.25, $slip('2026-11')['usd_total']);
        $this->assertEquals(-500.25, $slip('2026-12')['usd_total']);
        DB::transaction(function () use ($payment) {
            $locked = SalesPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            CommissionDisputes::transition($locked, 'Payment Success');
            $locked->update(['status' => 'Payment Success']);
            SalesActivitySync::sync($locked);
        });
        $this->assertEquals(500.25, CommissionAdjustment::latest('id')->first()->usd_amount);
        $this->assertEquals(0, $slip('2026-12')['usd_total']);
    }

    public function test_legacy_reconciliation_rejects_unknown_phrems_status_without_changing_the_existing_deduction(): void
    {
        $payment = $this->sale();
        Http::fakeSequence()->push($this->payload($payment))->push($this->payload($payment, 'unknown'));
        $entry = $this->makeLegacyDeduction($payment);
        $before = $entry->getAttributes();
        $this->post(route('reports.commission-releases.reconcile', $entry->id), [
            'disputed_at' => '2026-11-06', 'reason' => 'Verify source commission',
        ])->assertSessionHasErrors('status');
        $this->assertSame($before, $entry->fresh()->getAttributes());
        $this->assertDatabaseCount('commission_adjustment_reconciliations', 0);
        $this->assertDatabaseCount('commission_adjustments', 1);
    }

    public function test_reconciliation_is_admin_only_and_rejects_invalid_dates_and_nonlegacy_entries(): void
    {
        $payment = $this->sale();
        Http::fakeSequence()->push($this->payload($payment));
        $entry = $this->makeLegacyDeduction($payment);
        $url = route('reports.commission-releases.reconcile', $entry->id);
        $params = ['disputed_at' => '2026-11-06', 'reason' => 'Confirm PHREMS release'];
        $this->actingAs($this->agent)->post($url, $params)->assertForbidden();
        $finance = Role::firstOrCreate(['name' => 'Finance'], ['slug' => 'finance', 'department' => 'Finance']);
        $user = User::factory()->create(['role_id' => $finance->id, 'department' => 'Finance']);
        $this->actingAs($user)->post($url, $params)->assertForbidden();
        $this->actingAs($this->admin);
        $this->post($url, array_replace($params, ['disputed_at' => '2026-10-01']))->assertSessionHasErrors('reconciliation');
        $this->post($url, array_replace($params, ['disputed_at' => '2026-12-01']))->assertSessionHasErrors('disputed_at');
        $this->post($url, array_replace($params, ['reason' => '']))->assertSessionHasErrors('reason');
        $entry->update(['settlement_basis' => 'released']);
        $this->post($url, $params)->assertSessionHasErrors('reconciliation');
        $entry->update(['settlement_basis' => null]);
        $payment->fresh()->update(['status' => 'Payment Success']);
        $this->post($url, $params)->assertSessionHasErrors('reconciliation');
        $this->assertDatabaseCount('commission_adjustment_reconciliations', 0);
        $this->assertDatabaseCount('commission_adjustments', 1);
        Http::assertSentCount(1);
    }

    public function test_bearer_request_includes_stable_ids_and_all_monthly_legacy_match_candidates(): void
    {
        $first = $this->sale();
        $second = $this->sale('2026-10-25');
        $payload = $this->payload($first, 'unreleased');
        $payload['data'][] = $this->payload($second, 'unreleased')['data'][0];
        Http::fake(['https://phrems.test/api/crm/commission-release-status' => Http::response($payload)]);
        app(PhremsCommissionReleases::class)->syncForActivity(SalesActivity::where('sales_payment_id', $first->id)->firstOrFail());
        Http::assertSent(function (Request $request) use ($first, $second) {
            $this->assertSame('POST', $request->method());
            $this->assertSame('https://phrems.test/api/crm/commission-release-status', $request->url());
            $this->assertTrue($request->hasHeader('Authorization', 'Bearer dedicated-release-token'));
            $this->assertFalse($request->hasHeader('X-HRIS-Token'));
            $this->assertSame('EMP-RELEASE', $request['hris_employee_id']);
            $this->assertSame('2026-10', $request['month']);
            $this->assertSame([$first->id, $second->id], array_column($request['items'], 'crm_payment_id'));
            foreach ($request['items'] as $item) {
                $this->assertSame($this->agent->id, $item['crm_agent_id']);
                foreach (['brand', 'author', 'book_title', 'service', 'payment_method', 'sold_date', 'sale_amount'] as $field) $this->assertNotEmpty($item[$field]);
            }
            return true;
        });
        $this->assertDatabaseCount('commission_releases', 2);
    }

    public function test_sent_dispute_uses_saved_phrems_usd_not_current_calculation(): void
    {
        $payment = $this->sale();
        Http::fake(['*' => Http::response($this->payload($payment))]);
        $this->dispute($payment);
        $entry = CommissionAdjustment::firstOrFail();
        $this->assertSame('released', $entry->settlement_basis);
        $this->assertEquals(-600, $entry->usd_amount);
        $this->assertSame('2026-11-06', $entry->effective_date->toDateString());
        $this->assertEquals(60, $entry->original_commission['settlement_breakdown']['exchange_rate']);
        $this->assertSame('phrems', CommissionRelease::first()->source);
    }

    public function test_unsent_dispute_cancels_original_period_instead_of_creating_current_debt(): void
    {
        $payment = $this->sale();
        Http::fake(['*' => Http::response($this->payload($payment, 'unreleased'))]);
        $this->dispute($payment);
        $entry = CommissionAdjustment::firstOrFail();
        $this->assertSame('unreleased', $entry->settlement_basis);
        $this->assertSame('2026-10-10', $entry->effective_date->toDateString());
        $this->assertEquals(-650, $entry->usd_amount);
    }

    public function test_unknown_legacy_match_blocks_even_with_an_older_manual_confirmation(): void
    {
        $payment = $this->sale();
        CommissionRelease::create(['sales_payment_id' => $payment->id, 'agent_id' => $this->agent->id, 'status' => 'unreleased', 'reason' => 'Old manual record']);
        Http::fake(['*' => Http::response($this->payload($payment, 'unknown'))]);
        try {
            $this->dispute($payment);
            $this->fail('Unknown must not be treated as unreleased or bypassed by a stale confirmation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
        $this->assertSame('Payment Success', $payment->fresh()->status);
        $this->assertNull(SalesActivity::first()->commission_snapshot);
        $this->assertDatabaseCount('commission_adjustments', 0);
    }

    public function test_unauthorized_incomplete_malformed_and_mismatched_responses_cannot_record_a_dispute(): void
    {
        $payment = $this->sale();
        $base = $this->payload($payment);
        $cases = [
            [[], 401],
            [array_replace($base, ['complete' => false]), 200],
            [array_replace($base, ['month' => '2026-11']), 200],
            [array_replace($base, ['hris_employee_id' => 'EMP-OTHER']), 200],
            [array_replace($base, ['release_basis' => 'paid']), 200],
            [array_replace($base, ['data' => []]), 200],
            [array_replace($base, ['data' => ['invalid']]), 200],
            [array_replace($base, ['data' => [['key' => '999:999', 'status' => 'unreleased']]]), 200],
            [array_replace_recursive($base, ['data' => [['breakdown' => ['usd_total' => -1]]]]), 200],
        ];
        foreach ($cases as [$payload, $status]) {
            Http::fake(['*' => Http::response($payload, $status)]);
            try {
                $this->dispute($payment);
                $this->fail('Invalid PHREMS response must block the dispute.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('status', $exception->errors());
            }
            $this->assertSame('Payment Success', $payment->fresh()->status);
            $this->assertDatabaseCount('commission_adjustments', 0);
            $this->assertDatabaseCount('commission_releases', 0);
        }
    }

    public function test_connection_failure_is_a_validation_error_without_financial_changes(): void
    {
        $payment = $this->sale();
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('Timeout'));
        try {
            $this->dispute($payment);
            $this->fail('Unavailable PHREMS must not imply unreleased.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('unavailable', $exception->getMessage());
        }
        $this->assertSame('Payment Success', $payment->fresh()->status);
        $this->assertDatabaseCount('commission_adjustments', 0);
    }

    public function test_response_errors_identify_the_cause_without_echoing_upstream_content(): void
    {
        $payment = $this->sale();
        $base = $this->payload($payment);
        $secret = 'sensitive-upstream-token-and-author';
        $cases = [
            [401, ['message' => $secret], 'authentication (HTTP 401)'],
            [403, ['message' => $secret], 'authentication (HTTP 403)'],
            [404, ['message' => $secret], 'HTTP 404'],
            [422, ['errors' => ['items.0.service' => [$secret], $secret => [$secret]]], 'Rejected fields: items.0.service.'],
            [302, $secret, 'redirected'],
            [429, ['message' => $secret], 'rate-limited'],
            [500, ['message' => $secret], 'HTTP 500'],
            [200, '<html>'.$secret.'</html>', 'non-JSON'],
            [200, array_replace($base, ['hris_employee_id' => $secret]), 'mismatched fields: hris_employee_id.'],
            [200, array_replace($base, ['complete' => false]), 'mismatched fields: complete.'],
        ];
        $sequence = Http::fakeSequence();
        foreach ($cases as [$status, $payload]) $sequence->push($payload, $status);
        foreach ($cases as [$status, $payload, $expected]) {
            try {
                $this->dispute($payment);
                $this->fail('Failed responses must block the dispute.');
            } catch (ValidationException $exception) {
                $this->assertStringContainsString($expected, $exception->getMessage());
                $this->assertStringNotContainsString($secret, $exception->getMessage());
                $this->assertStringNotContainsString('dedicated-release-token', $exception->getMessage());
            }
            $this->assertSame('Payment Success', $payment->fresh()->status);
            $this->assertDatabaseCount('commission_adjustments', 0);
            $this->assertDatabaseCount('commission_releases', 0);
        }
    }

    public function test_fetch_is_admin_only_idempotent_and_never_silently_downgrades_a_sent_slip(): void
    {
        $payment = $this->sale();
        Http::fakeSequence()->push($this->payload($payment))->push($this->payload($payment))
            ->push($this->payload($payment, 'unreleased'));
        $route = route('reports.commission-releases.sync');
        $params = ['month' => '2026-10', 'agent_id' => $this->agent->id];
        $this->actingAs($this->agent)->post($route, $params)->assertForbidden();
        $finance = Role::firstOrCreate(['name' => 'Finance'], ['slug' => 'finance', 'department' => 'Finance']);
        $user = User::factory()->create(['role_id' => $finance->id, 'department' => 'Finance']);
        $this->actingAs($user)->post($route, $params)->assertForbidden();
        Http::assertNothingSent();
        $this->actingAs($this->admin)->post($route, $params)->assertSessionHasNoErrors();
        $this->post($route, $params)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('commission_releases', 1);
        $this->post($route, $params)->assertSessionHasNoErrors();
        $this->assertSame('unknown', CommissionRelease::latestFor($payment->id, $this->agent->id)->status);
        $this->assertDatabaseCount('commission_adjustments', 0);
        $this->get(route('reports.commission-releases.index', $params))->assertOk()->assertSee('Needs review')->assertSee('Fetch status from PHREMS');
    }
}
