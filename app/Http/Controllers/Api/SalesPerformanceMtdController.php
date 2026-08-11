<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
        $configuredToken = (string) config('services.hris.token');
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

        $month = Carbon::createFromFormat('Y-m', $validated['month'] ?? now()->format('Y-m'))->startOfMonth();
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
            ->with('brand')
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
                    'service_commission_percent' => SalesMtdCalculator::SERVICE_RATE_LOW,
                    'markup_commission_percent' => (float) ($agent->markup_commission_percent ?? 50),
                ]);
                $targetAmount = (float) ($target?->amount ?? 0);
                $mtd = (float) $credit['mtd'];

                return [
                    'agent_id' => $agent->id,
                    'agent_name' => trim($agent->first_name.' '.$agent->last_name),
                    'email' => $agent->email,
                    'brand_id' => $agent->brand_id,
                    'brand_name' => $agent->brand?->imprint_name,
                    'work_type' => match ($agent->work_type) {
                        'remote' => 'remote',
                        'site' => 'on-site',
                        'part_time' => 'part-time',
                        default => null,
                    },
                    'mtd' => round($mtd, 2),
                    'service_mtd' => round((float) $credit['service_mtd'], 2),
                    'commissionable_service_mtd' => round((float) ($credit['commissionable_service_mtd'] ?? 0), 2),
                    'threshold_applied_amount' => round((float) ($credit['threshold_applied_amount'] ?? 0), 2),
                    'markup_mtd' => round((float) $credit['markup_mtd'], 2),
                    'target' => round($targetAmount, 2),
                    'mtd_percent' => $targetAmount > 0 ? round(($mtd / $targetAmount) * 100, 2) : 0,
                    'remaining_target' => round(max($targetAmount - $mtd, 0), 2),
                    'service_commission_percent' => round((float) ($credit['service_commission_percent'] ?? SalesMtdCalculator::SERVICE_RATE_LOW), 2),
                    'markup_commission_percent' => round((float) ($credit['markup_commission_percent'] ?? $agent->markup_commission_percent ?? 50), 2),
                    'commission_threshold_amount' => round((float) ($agent->commission_threshold_amount ?? SalesMtdCalculator::DEFAULT_SERVICE_THRESHOLD), 2),
                    'is_commission_threshold_exempt' => (bool) $agent->is_commission_threshold_exempt,
                    'service_comm' => round((float) $credit['service_commission'], 2),
                    'markup_comm' => round((float) $credit['markup_commission'], 2),
                    'usd_total' => round((float) $credit['usd_total'], 2),
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
                'work_type',
                'mtd',
                'service_mtd',
                'commissionable_service_mtd',
                'markup_mtd',
                'target',
                'mtd_percent',
                'service_comm',
                'markup_comm',
                'usd_total',
            ],
            'summary' => [
                'global' => $summary['global'],
                'remote' => $summary['remote'],
                'site' => $summary['site'],
                'hris_note' => 'Use usd_total for HRIS PHP total, hold percentage, and net commission calculations.',
            ],
            'agents' => $agents,
        ]);
    }
}
