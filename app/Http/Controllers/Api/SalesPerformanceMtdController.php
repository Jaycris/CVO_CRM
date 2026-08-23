<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Brand;
use App\Models\User;
use App\Support\SalesMtdCalculator;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesPerformanceMtdController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $configuredToken = AppSetting::hrisApiToken();
        $providedToken = (string) ($request->bearerToken() ?: $request->header('X-HRIS-Token'));

        abort_if(
            $configuredToken === '' || ! hash_equals($configuredToken, $providedToken),
            403,
            'Invalid HRIS API token.'
        );

        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'brand' => ['nullable', 'string', 'max:255'],
        ]);

        $month = Carbon::createFromFormat('!Y-m', $validated['month'] ?? now()->format('Y-m'))->startOfMonth();
        $brandId = $validated['brand_id'] ?? null;

        if (! $brandId && filled($validated['brand'] ?? null)) {
            $brandId = Brand::query()
                ->where('imprint_name', $validated['brand'])
                ->value('id');
        }

        $summary = SalesMtdCalculator::summary(null, $month, $brandId);
        $visibleAgentIds = $summary['agentCredits']->keys()
            ->merge($summary['agentTargets']->keys())
            ->filter()
            ->unique()
            ->values();
        $agents = User::query()
            ->with(['brand', 'commissionProfile.rules'])
            ->where(function ($query) use ($visibleAgentIds) {
                $query->where('department', 'Sales');

                if ($visibleAgentIds->isNotEmpty()) {
                    $query->orWhereIn('id', $visibleAgentIds);
                }
            })
            ->when($brandId, fn ($query) => $query->where('brand_id', $brandId))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(function (User $agent) use ($summary) {
                $target = $summary['agentTargets']->get($agent->id);
                $credit = $summary['agentCredits']->get($agent->id, [
                    'mtd' => 0,
                    'service_mtd' => 0,
                    'markup_mtd' => 0,
                    'commissionable_service_mtd' => 0,
                    'threshold_applied_amount' => 0,
                    'service_commission' => 0,
                    'markup_commission' => 0,
                    'usd_total' => 0,
                    'php_total' => 0,
                    'exchange_rate' => $summary['exchangeRate'],
                    'card_payment_hold_percent' => $summary['cardPaymentHoldPercent'],
                    'hold_amount' => 0,
                    'net_commission' => 0,
                    'service_commission_percent' => SalesMtdCalculator::SERVICE_RATE_LOW,
                    'markup_commission_percent' => (float) ($agent->markup_commission_percent ?? 50),
                ]);
                $targetAmount = (float) ($target?->amount ?? 0);
                $mtd = (float) $credit['mtd'];

                return [
                    'agent_id' => $agent->id,
                    'hris_employee_id' => $agent->hris_employee_id,
                    'agent_name' => trim($agent->first_name.' '.$agent->last_name),
                    'email' => $agent->email,
                    'brand_id' => $agent->brand_id,
                    'brand_name' => $agent->brand?->imprint_name,
                    'work_type' => match ($agent->work_type) {
                        'remote' => 'remote',
                        'hybrid' => 'hybrid',
                        'site' => 'on-site',
                        default => null,
                    },
                    'mtd' => round($mtd, 2),
                    'service_mtd' => round((float) $credit['service_mtd'], 2),
                    'commissionable_service_mtd' => round((float) ($credit['commissionable_service_mtd'] ?? 0), 2),
                    'threshold_applied_amount' => round((float) ($credit['threshold_applied_amount'] ?? 0), 2),
                    'markup_mtd' => round((float) $credit['markup_mtd'], 2),
                    'target' => round($targetAmount, 2),
                    'agent_target' => round($targetAmount, 2),
                    'commission_scheme' => $this->commissionScheme($agent),
                    'mtd_percent' => $targetAmount > 0 ? round(($mtd / $targetAmount) * 100, 2) : 0,
                    'remaining_target' => round(max($targetAmount - $mtd, 0), 2),
                    'service_commission_percent' => round((float) ($credit['service_commission_percent'] ?? SalesMtdCalculator::SERVICE_RATE_LOW), 2),
                    'markup_commission_percent' => round((float) ($credit['markup_commission_percent'] ?? $agent->markup_commission_percent ?? 50), 2),
                    'commission_threshold_amount' => round((float) ($agent->commission_threshold_amount ?? SalesMtdCalculator::DEFAULT_SERVICE_THRESHOLD), 2),
                    'is_commission_threshold_exempt' => (bool) $agent->is_commission_threshold_exempt,
                    'service_comm' => round((float) $credit['service_commission'], 2),
                    'markup_comm' => round((float) $credit['markup_commission'], 2),
                    'usd_total' => round((float) $credit['usd_total'], 2),
                    'exchange_rate' => round((float) ($credit['exchange_rate'] ?? $summary['exchangeRate']), 4),
                    'php_total' => round((float) ($credit['php_total'] ?? 0), 2),
                    'card_payment_hold_percent' => round((float) ($credit['card_payment_hold_percent'] ?? $summary['cardPaymentHoldPercent']), 2),
                    'hold_amount' => round((float) ($credit['hold_amount'] ?? 0), 2),
                    'net_commission' => round((float) ($credit['net_commission'] ?? 0), 2),
                ];
            })
            ->values();

        return response()->json([
            'data_type' => 'agent_sales_performance_mtd',
            'month' => $month->format('Y-m'),
            'generated_at' => now()->toIso8601String(),
            'brand_id' => $brandId,
            'brand_name' => $brandId ? Brand::query()->whereKey($brandId)->value('imprint_name') : 'All Brands',
            'columns' => [
                'agent_name',
                'hris_employee_id',
                'work_type',
                'mtd',
                'service_mtd',
                'commissionable_service_mtd',
                'markup_mtd',
                'target',
                'agent_target',
                'commission_scheme',
                'mtd_percent',
                'service_comm',
                'markup_comm',
                'usd_total',
                'exchange_rate',
                'php_total',
                'card_payment_hold_percent',
                'hold_amount',
                'net_commission',
            ],
            'summary' => [
                'global' => $summary['global'],
                'remote' => $summary['remote'],
                'hybrid' => $summary['hybrid'],
                'site' => $summary['site'],
                'exchange_rate' => round((float) $summary['exchangeRate'], 4),
                'card_payment_hold_percent' => round((float) $summary['cardPaymentHoldPercent'], 2),
                'hris_note' => 'Use php_total for HRIS PHP total, hold percentage, and net commission calculations.',
            ],
            'agents' => $agents,
        ]);
    }

    private function commissionScheme(User $agent): array
    {
        $profile = $agent->commissionProfile;

        return [
            'name' => $profile?->name ?? 'Default Service Tiers',
            'rules' => $profile?->rules
                ? $profile->rules
                    ->sortBy('minimum_mtd_percent')
                    ->map(fn ($rule) => [
                        'minimum_mtd_percent' => round((float) $rule->minimum_mtd_percent, 2),
                        'commission_percent' => round((float) $rule->commission_percent, 2),
                    ])
                    ->values()
                    ->all()
                : [
                    ['minimum_mtd_percent' => 0, 'commission_percent' => 15],
                    ['minimum_mtd_percent' => 75, 'commission_percent' => 20],
                    ['minimum_mtd_percent' => 100, 'commission_percent' => 25],
                ],
        ];
    }
}
