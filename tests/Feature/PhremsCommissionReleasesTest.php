<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Brand;
use App\Models\CommissionAdjustment;
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
