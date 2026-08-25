<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\SalesActivity;
use App\Models\SalesTarget;
use App\Models\User;
use App\Support\SalesMtdCalculator;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CommissionSlipController extends Controller
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
            'agent' => ['nullable', 'string', 'max:50'],
            'hris_employee_id' => ['nullable', 'string', 'max:50'],
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $hrisEmployeeId = trim((string) ($validated['hris_employee_id'] ?? $validated['agent'] ?? ''));

        abort_if($hrisEmployeeId === '', 422, 'The HRIS employee ID is required.');

        $month = Carbon::createFromFormat('!Y-m', $validated['month'] ?? now()->format('Y-m'))->startOfMonth();
        $agent = User::query()
            ->with(['brand', 'team', 'commissionProfile.rules'])
            ->where('hris_employee_id', $hrisEmployeeId)
            ->first();

        abort_if(! $agent, 404, 'No CRM user is linked to this HRIS employee ID.');

        $activities = SalesActivity::query()
            ->with(['brand', 'agent', 'frankieAgent', 'service'])
            ->where('payment_status', 'Payment Success')
            ->whereBetween('sold_date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->where(function ($query) use ($agent) {
                $query->where('agent_id', $agent->id)
                    ->orWhere('frankie_agent_id', $agent->id);
            })
            ->latest('sold_date')
            ->latest('id')
            ->get();

        $targets = SalesTarget::query()
            ->whereDate('target_month', $month->toDateString())
            ->where('target_type', 'agent')
            ->where('user_id', $agent->id)
            ->get();

        $agentTargetRows = SalesMtdCalculator::agentTargetRows($targets);
        $selectedTargets = $agentTargetRows->values();
        $isCommissionEligible = (bool) $agent->is_commission_eligible;
        $agentTargetAmount = $isCommissionEligible ? (float) $selectedTargets->sum('amount') : 0.0;
        $rows = SalesMtdCalculator::statementRows($activities, $agentTargetRows)
            ->where('agent_id', $agent->id)
            ->sortByDesc(fn (array $row) => $row['activity']->sold_date?->timestamp ?? 0)
            ->values();

        return response()->json([
            'agent' => [
                'name' => trim($agent->first_name.' '.$agent->last_name),
                'team' => $agent->team?->name,
                'work_type' => $this->workArrangementLabel($agent->work_type),
                'is_commission_eligible' => $isCommissionEligible,
                'commission_scheme' => $this->commissionScheme($agent, $isCommissionEligible),
                'agent_target' => round($agentTargetAmount, 2),
                'commission_threshold_amount' => round($this->thresholdAmount($agent, $isCommissionEligible), 2),
                'is_commission_threshold_exempt' => (bool) $agent->is_commission_threshold_exempt,
            ],
            'month' => $month->format('Y-m'),
            'hris_employee_id' => $agent->hris_employee_id,
            'is_commission_eligible' => $isCommissionEligible,
            'agent_target' => round($agentTargetAmount, 2),
            'commission_scheme' => $this->commissionScheme($agent, $isCommissionEligible),
            'commission_threshold_amount' => round($this->thresholdAmount($agent, $isCommissionEligible), 2),
            'is_commission_threshold_exempt' => (bool) $agent->is_commission_threshold_exempt,
            'summary' => $this->summary($rows, $agentTargetAmount, $agent, $isCommissionEligible),
            'transactions' => $this->transactions($rows),
        ]);
    }

    private function summary(Collection $rows, float $targetAmount, User $agent, bool $isCommissionEligible): array
    {
        $mtd = (float) $rows->sum('amount');

        return [
            'mtd' => round($mtd, 2),
            'target' => round($targetAmount, 2),
            'agent_target' => round($targetAmount, 2),
            'is_commission_eligible' => $isCommissionEligible,
            'commission_scheme' => $this->commissionScheme($agent, $isCommissionEligible),
            'commission_threshold_amount' => round($this->thresholdAmount($agent, $isCommissionEligible), 2),
            'is_commission_threshold_exempt' => (bool) $agent->is_commission_threshold_exempt,
            'threshold_applied_amount' => round((float) $rows->sum('threshold_applied_amount'), 2),
            'threshold_applied_service_amount' => round((float) $rows->sum('threshold_applied_service_amount'), 2),
            'threshold_applied_markup_amount' => round((float) $rows->sum('threshold_applied_markup_amount'), 2),
            'mtd_percent' => $targetAmount > 0 ? round(($mtd / $targetAmount) * 100, 2) : 0,
            'service_commission' => round((float) $rows->sum('service_commission'), 2),
            'markup_commission' => round((float) $rows->sum('markup_commission'), 2),
            'usd_total' => round((float) $rows->sum('usd_total'), 2),
            'exchange_rate' => round(AppSetting::commissionExchangeRate(), 4),
            'php_total' => round((float) $rows->sum('php_total'), 2),
            'card_payment_hold_percent' => round(AppSetting::cardPaymentHoldPercent(), 2),
            'card_payment_hold_amount' => round((float) $rows->sum('hold_amount'), 2),
            'net_commission' => round((float) $rows->sum('net_commission'), 2),
        ];
    }

    private function transactions(Collection $rows): array
    {
        return $rows
            ->map(function (array $row) {
                $activity = $row['activity'];

                return [
                    'sold_date' => $activity->sold_date?->format('Y-m-d'),
                    'brand' => $activity->brand?->imprint_name,
                    'author' => $activity->author_name,
                    'book_title' => $activity->book_title,
                    'service' => $activity->service_name ?: $activity->service?->name,
                    'payment_method' => $activity->payment_method,
                    'sale_amount' => round((float) $row['sale_amount'], 2),
                    'service_amount' => round((float) $row['service_amount'], 2),
                    'markup_amount' => round((float) $row['markup_amount'], 2),
                    'threshold_applied_amount' => round((float) ($row['threshold_applied_amount'] ?? 0), 2),
                    'threshold_applied_service_amount' => round((float) ($row['threshold_applied_service_amount'] ?? 0), 2),
                    'threshold_applied_markup_amount' => round((float) ($row['threshold_applied_markup_amount'] ?? 0), 2),
                    'service_commission' => round((float) $row['service_commission'], 2),
                    'markup_commission' => round((float) $row['markup_commission'], 2),
                    'usd_total' => round((float) $row['usd_total'], 2),
                    'php_total' => round((float) $row['php_total'], 2),
                    'card_hold_amount' => round((float) $row['hold_amount'], 2),
                    'net_commission' => round((float) $row['net_commission'], 2),
                ];
            })
            ->values()
            ->all();
    }

    private function workArrangementLabel(?string $workType): ?string
    {
        return match ($workType) {
            'remote' => 'Remote',
            'hybrid' => 'Hybrid',
            'site' => 'On-site',
            default => null,
        };
    }

    private function commissionScheme(User $agent, bool $isCommissionEligible): ?array
    {
        if (! $isCommissionEligible) {
            return null;
        }

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

    private function thresholdAmount(User $agent, bool $isCommissionEligible): float
    {
        if (! $isCommissionEligible || $agent->is_commission_threshold_exempt) {
            return 0;
        }

        return (float) ($agent->commission_threshold_amount ?? SalesMtdCalculator::DEFAULT_SERVICE_THRESHOLD);
    }
}
