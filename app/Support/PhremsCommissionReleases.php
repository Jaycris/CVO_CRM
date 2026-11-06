<?php

namespace App\Support;

use App\Models\AppSetting;
use App\Models\CommissionRelease;
use App\Models\SalesActivity;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class PhremsCommissionReleases
{
    public function syncForActivity(SalesActivity $activity): void
    {
        $agentIds = array_unique(array_filter([$activity->agent_id,
            (float) $activity->frankie_credit_amount > 0 ? $activity->frankie_agent_id : null]));
        foreach ($agentIds as $agentId) {
            $statuses = $this->syncAgentMonth(User::withTrashed()->findOrFail($agentId), $activity->sold_date->copy()->startOfMonth());
            if (! in_array($statuses[$activity->sales_payment_id.':'.$agentId] ?? null, ['released', 'unreleased'], true)) {
                $this->fail('PHREMS could not uniquely identify this payment on its sent commission slips. Ask Admin to review Commission Releases.');
            }
        }
    }

    public function syncMonth(CarbonInterface $month, ?int $agentId = null): int
    {
        $activities = $this->activities($month)->when($agentId, fn ($query) => $query->where(fn ($query) => $query->where('agent_id', $agentId)->orWhere('frankie_agent_id', $agentId)))->get();
        $ids = $activities->flatMap(fn ($activity) => [$activity->agent_id, (float) $activity->frankie_credit_amount > 0 ? $activity->frankie_agent_id : null])
            ->filter()->unique()->when($agentId, fn ($ids) => $ids->filter(fn ($id) => $id == $agentId));
        $count = 0;
        foreach (User::withTrashed()->whereIn('id', $ids)->get() as $agent) $count += count($this->syncAgentMonth($agent, $month));
        return $count;
    }

    private function activities(CarbonInterface $month)
    {
        return SalesActivity::with(['brand', 'service'])->where('payment_status', 'Payment Success')->whereNotNull('sales_payment_id')
            ->whereDate('sold_date', '>=', $month->copy()->startOfMonth()->toDateString())
            ->whereDate('sold_date', '<=', $month->copy()->endOfMonth()->toDateString());
    }

    private function syncAgentMonth(User $agent, CarbonInterface $month): array
    {
        $baseUrl = rtrim(AppSetting::hrisBaseUrl(), '/');
        $token = (string) config('services.hris.commission_release_token');
        if ($baseUrl === '' || $token === '' || ! $agent->hris_employee_id) {
            $this->fail('Configure the PHREMS commission-release token and link the agent’s HRIS employee ID before fetching release status.');
        }
        $activities = $this->activities($month)->where(fn ($query) => $query->where('agent_id', $agent->id)
            ->orWhere(fn ($query) => $query->where('frankie_agent_id', $agent->id)->where('frankie_credit_amount', '>', 0)))->get();
        $items = $activities->map(fn ($activity) => [
            'key' => $activity->sales_payment_id.':'.$agent->id, 'crm_payment_id' => $activity->sales_payment_id, 'crm_agent_id' => $agent->id,
            'sold_date' => $activity->sold_date->toDateString(), 'brand' => $activity->brand?->imprint_name ?? '',
            'author' => $activity->author_name, 'book_title' => $activity->book_title,
            'service' => $activity->service_name ?: $activity->service?->name,
            'payment_method' => $activity->payment_method, 'sale_amount' => (float) $activity->amount,
        ])->values()->all();
        if ($items === []) return [];
        if (count($items) > 500 || count(array_unique(array_column($items, 'key'))) !== count($items)) {
            $this->fail('The PHREMS lookup cannot safely match this month’s payment records. Ask Admin to review duplicate or excessive records.');
        }
        try {
            $response = Http::connectTimeout(3)->timeout(10)->withoutRedirecting()->acceptJson()->withToken($token)
                ->post($baseUrl.'/api/crm/commission-release-status', ['hris_employee_id' => $agent->hris_employee_id, 'month' => $month->format('Y-m'), 'items' => $items]);
        } catch (\Illuminate\Http\Client\ConnectionException $exception) {
            $this->fail('PHREMS is unavailable. Release status has not been assumed; retry before recording the dispute.');
        }
        $payload = $response->json();
        if (! $response->successful() || ! is_array($payload) || ($payload['complete'] ?? false) !== true
            || ($payload['hris_employee_id'] ?? null) !== $agent->hris_employee_id || ($payload['month'] ?? null) !== $month->format('Y-m')
            || ($payload['release_basis'] ?? null) !== 'sent' || ! is_array($payload['data'] ?? null)) {
            $this->fail('PHREMS did not return a complete, verified commission-release response. Check its endpoint and token before continuing.');
        }
        $results = collect($payload['data']);
        if ($results->contains(fn ($result) => ! is_array($result) || ! is_string($result['key'] ?? null))) {
            $this->fail('PHREMS returned invalid payment results. No release statuses were inferred.');
        }
        $keys = $results->pluck('key');
        if ($results->count() !== count($items) || $keys->unique()->count() !== $keys->count()
            || $keys->diff(array_column($items, 'key'))->isNotEmpty()) {
            $this->fail('PHREMS returned incomplete or mismatched payment results. No release statuses were inferred.');
        }
        foreach ($results as $result) {
            if (! in_array($result['status'] ?? null, ['released', 'unreleased', 'unknown'], true)) $this->fail('PHREMS returned an invalid release status.');
            if ($result['status'] !== 'released') continue;
            if (empty($result['reference']) || ! is_string($result['reference']) || strlen($result['reference']) > 255
                || empty($result['released_at']) || ! is_string($result['released_at'])) $this->fail('PHREMS returned an unverified sent-slip reference.');
            try { $sentAt = Carbon::parse($result['released_at']); } catch (\Throwable $exception) { $this->fail('PHREMS returned an invalid sent date.'); }
            if ($sentAt->isFuture()) $this->fail('PHREMS returned a sent date in the future.');
            foreach (['usd_total', 'service_commission', 'markup_commission', 'php_total', 'hold_amount', 'net_commission', 'exchange_rate', 'card_payment_hold_percent'] as $field) {
                $value = $result['breakdown'][$field] ?? null;
                if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value < 0) $this->fail('PHREMS returned an incomplete sent commission breakdown.');
            }
            if ((float) $result['breakdown']['exchange_rate'] <= 0) $this->fail('PHREMS returned an invalid sent exchange rate.');
        }
        $statuses = [];
        foreach ($results as $result) {
            [$paymentId] = explode(':', $result['key']);
            $last = CommissionRelease::latestFor((int) $paymentId, $agent->id);
            $status = $result['status'];
            $reason = 'Fetched from PHREMS; Sent is treated as Released.';
            if ($status === 'unreleased' && CommissionRelease::where('sales_payment_id', $paymentId)->where('agent_id', $agent->id)->where('status', 'released')->exists()) {
                $status = 'unknown';
                $reason = 'Previously released commission is no longer identifiable in PHREMS. Review the source slip.';
            } elseif ($status === 'unknown') {
                $reason = 'PHREMS could not uniquely match the payment to a sent slip.';
            }
            $reference = $status === 'released' ? $result['reference'] : null;
            $date = $status === 'released' ? Carbon::parse($result['released_at'])->toDateString() : null;
            $breakdown = $status === 'released' ? collect($result['breakdown'])->only([
                'usd_total', 'service_commission', 'markup_commission', 'php_total',
                'hold_amount', 'net_commission', 'exchange_rate', 'card_payment_hold_percent',
            ])->map(fn ($value) => (float) $value)->all() : null;
            $attributes = ['sales_payment_id' => (int) $paymentId, 'agent_id' => $agent->id, 'brand_id' => $activities->firstWhere('sales_payment_id', (int) $paymentId)->brand_id,
                'status' => $status, 'source' => 'phrems', 'source_reference' => $reference, 'released_at' => $date,
                'commission_breakdown' => $breakdown, 'reason' => $reason, 'created_by' => auth()->id()];
            if (! ($last && $last->source === 'phrems' && $last->status === $status && $last->source_reference === $reference
                && $last->released_at?->toDateString() === $date && $last->commission_breakdown == $breakdown && $last->reason === $reason)) {
                $hash = hash('sha256', json_encode([$paymentId, $agent->id, $last?->id, $status, $reference, $date, $breakdown, $reason]));
                CommissionRelease::firstOrCreate(['source_key' => $hash], $attributes);
            }
            $statuses[$result['key']] = $status;
        }
        return $statuses;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['status' => $message]);
    }
}
