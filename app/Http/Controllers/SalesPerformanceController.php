<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\SalesTarget;
use App\Models\Team;
use App\Models\User;
use App\Support\BrandScope;
use App\Support\SalesMtdCalculator;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class SalesPerformanceController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $canManageTargets = $this->canManageTargets($user);
        $canViewDashboard = $user?->role?->name === 'Admin'
            || $user?->department === 'Sales'
            || $user?->hasPermission('view_sales_performance_mtd')
            || $canManageTargets;
        $canViewAllRows = $user?->role?->name === 'Admin'
            || $user?->hasPermission('view_all_agent_mtd_directory')
            || $canManageTargets;

        abort_unless($canViewDashboard, 403);

        $month = $this->monthFromRequest($request);
        $isAdmin = $user?->role?->name === 'Admin';
        $canUseSalesBrandFilter = $isAdmin
            || (BrandScope::canAccessAllBrands($user)
                && ($user?->hasPermission('view_all_agent_mtd_directory') || $canManageTargets));
        $salesBrands = $canUseSalesBrandFilter
            ? Brand::query()->where('is_sales_brand', true)->orderBy('imprint_name')->get()
            : collect();
        $requestedBrandId = $request->integer('brand_id') ?: null;
        $brandId = $canUseSalesBrandFilter
            ? ($salesBrands->contains('id', $requestedBrandId) ? $requestedBrandId : null)
            : BrandScope::userBrandId($user);
        $search = trim((string) $request->query('search', ''));
        $includeOwnCreditsAcrossBrands = ! BrandScope::canAccessAllBrands($user)
            && $user?->department === 'Sales'
            && ! $brandId;
        $summaryUser = $canUseSalesBrandFilter ? null : $user;
        $summary = SalesMtdCalculator::summary($summaryUser, $month, $brandId, $includeOwnCreditsAcrossBrands, $canUseSalesBrandFilter && ! $brandId);
        $teamTargets = SalesTarget::query()
            ->whereDate('target_month', $month->toDateString())
            ->where('target_type', 'team')
            ->whereNotNull('team_id')
            ->when($brandId, fn ($query) => $query->where('brand_id', $brandId))
            ->when($canUseSalesBrandFilter && ! $brandId, function ($query) {
                $query->where(function ($query) {
                    $query->whereNull('brand_id')
                        ->orWhereHas('brand', fn ($query) => $query->where('is_sales_brand', true));
                });
            })
            ->when(! $brandId && ! $canUseSalesBrandFilter, fn ($query) => BrandScope::apply($query, $user))
            ->get()
            ->keyBy('team_id');
        $targetTeams = Team::query()
            ->with(['brand', 'members' => fn ($query) => $query
                ->where('department', 'Sales')
                ->where('is_commission_eligible', true)
                ->select(['id', 'team_id', 'first_name', 'last_name'])])
            ->where('department', 'Sales')
            ->where('show_on_sales_dashboard', true)
            ->whereHas('brand', fn ($query) => $query->where('is_sales_brand', true))
            ->when($brandId, fn ($query) => $query->where('brand_id', $brandId))
            ->when($canUseSalesBrandFilter && ! $brandId, fn ($query) => $query->whereHas('brand', fn ($query) => $query->where('is_sales_brand', true)))
            ->when(! $brandId && ! $canUseSalesBrandFilter, fn ($query) => BrandScope::apply($query, $user))
            ->orderBy('name')
            ->get();
        $visibleAgentIds = $summary['agentCredits']->keys()
            ->merge($summary['agentTargets']->keys())
            ->filter()
            ->unique()
            ->values();

        $agentsQuery = User::query()
            ->with(['brand', 'role', 'commissionProfile', 'team'])
            ->where(function ($query) use ($visibleAgentIds) {
                $query->where('department', 'Sales');

                if ($visibleAgentIds->isNotEmpty()) {
                    $query->orWhereIn('id', $visibleAgentIds);
                }
            })
            ->when($brandId, fn ($query) => $query->where('brand_id', $brandId))
            ->when($canUseSalesBrandFilter && ! $brandId, fn ($query) => $query->whereHas('brand', fn ($query) => $query->where('is_sales_brand', true)))
            ->when(! $brandId && ! $canUseSalesBrandFilter, fn ($query) => BrandScope::apply($query, $user))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('brand', fn ($query) => $query->where('imprint_name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('first_name')
            ->orderBy('last_name');

        $agentRows = $agentsQuery->get()->map(function (User $agent) use ($summary) {
            $target = $summary['agentTargets']->get($agent->id);
            $credit = $summary['agentCredits']->get($agent->id, [
                'mtd' => 0,
                'service_mtd' => 0,
                'markup_mtd' => 0,
                'commissionable_service_mtd' => 0,
                'threshold_applied_amount' => 0,
                'threshold_applied_service_amount' => 0,
                'threshold_applied_markup_amount' => 0,
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
            $mtd = (float) $credit['mtd'];
            $targetAmount = (float) ($target?->amount ?? 0);
            $isSalesTeamAgent = $agent->department === 'Sales'
                && $agent->team?->department === 'Sales'
                && (bool) $agent->team?->show_on_sales_dashboard
                && (bool) $agent->is_commission_eligible;

            return [
                'id' => $agent->id,
                'agent' => $agent,
                'team_id' => $isSalesTeamAgent ? $agent->team?->id : null,
                'team_name' => $agent->team?->name,
                'sales_team_name' => $isSalesTeamAgent ? $agent->team?->name : null,
                'work_type' => $agent->work_type,
                'mtd' => $mtd,
                'service_mtd' => (float) $credit['service_mtd'],
                'markup_mtd' => (float) $credit['markup_mtd'],
                'commissionable_service_mtd' => (float) ($credit['commissionable_service_mtd'] ?? 0),
                'threshold_applied_amount' => (float) ($credit['threshold_applied_amount'] ?? 0),
                'threshold_applied_service_amount' => (float) ($credit['threshold_applied_service_amount'] ?? 0),
                'threshold_applied_markup_amount' => (float) ($credit['threshold_applied_markup_amount'] ?? 0),
                'service_commission' => (float) $credit['service_commission'],
                'markup_commission' => (float) $credit['markup_commission'],
                'usd_total' => (float) $credit['usd_total'],
                'php_total' => (float) ($credit['php_total'] ?? 0),
                'exchange_rate' => (float) ($credit['exchange_rate'] ?? $summary['exchangeRate']),
                'card_payment_hold_percent' => (float) ($credit['card_payment_hold_percent'] ?? $summary['cardPaymentHoldPercent']),
                'hold_amount' => (float) ($credit['hold_amount'] ?? 0),
                'net_commission' => (float) ($credit['net_commission'] ?? 0),
                'service_commission_percent' => (float) ($credit['service_commission_percent'] ?? SalesMtdCalculator::SERVICE_RATE_LOW),
                'commission_profile_name' => (string) ($credit['commission_profile_name'] ?? $agent->commissionProfile?->name ?? 'Default Service Tiers'),
                'markup_commission_percent' => (float) ($credit['markup_commission_percent'] ?? $agent->markup_commission_percent ?? 50),
                'commission_threshold_amount' => (float) ($agent->commission_threshold_amount ?? SalesMtdCalculator::DEFAULT_SERVICE_THRESHOLD),
                'is_commission_threshold_exempt' => (bool) $agent->is_commission_threshold_exempt,
                'is_commission_eligible' => (bool) $agent->is_commission_eligible,
                'target' => $targetAmount,
                'remaining' => max($targetAmount - $mtd, 0),
                'percent' => $targetAmount > 0 ? round(($mtd / $targetAmount) * 100, 2) : 0,
            ];
        })->filter(function (array $row) use ($canViewAllRows, $user) {
            if ($canViewAllRows) {
                return $row['is_commission_eligible'] || $row['mtd'] > 0;
            }

            return $row['mtd'] > 0 || (int) $row['id'] === (int) $user?->id;
        })->sort(function (array $first, array $second) {
            $mtdComparison = $second['mtd'] <=> $first['mtd'];

            if ($mtdComparison !== 0) {
                return $mtdComparison;
            }

            $firstName = trim(($first['agent']->first_name ?? '').' '.($first['agent']->last_name ?? ''));
            $secondName = trim(($second['agent']->first_name ?? '').' '.($second['agent']->last_name ?? ''));

            return strcasecmp($firstName, $secondName);
        })->values()->map(function (array $row, int $index) {
            $row['rank'] = $index + 1;

            return $row;
        })->values();

        $teamRows = $targetTeams
            ->map(function (Team $team) use ($summary, $teamTargets) {
                $memberIds = $team->members->pluck('id');

                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'mtd' => (float) $memberIds->sum(fn ($agentId) => (float) ($summary['agentCredits']->get($agentId)['mtd'] ?? 0)),
                    'target' => (float) ($teamTargets->get($team->id)?->amount ?? 0),
                    'members' => $memberIds->count(),
                ];
            })
            ->map(function (array $team) {
                $team['remaining'] = max($team['target'] - $team['mtd'], 0);
                $team['percent'] = $team['target'] > 0 ? round(($team['mtd'] / $team['target']) * 100, 2) : 0;

                return $team;
            })
            ->sortByDesc('mtd')
            ->values();

        $teamGlobalTarget = (float) $teamRows->sum('target');
        $globalMtd = (float) ($summary['global']['mtd'] ?? 0);
        $summary['global'] = [
            'mtd' => $globalMtd,
            'target' => $teamGlobalTarget,
            'remaining' => max($teamGlobalTarget - $globalMtd, 0),
            'percent' => $teamGlobalTarget > 0 ? round(($globalMtd / $teamGlobalTarget) * 100, 2) : 0,
        ];

        $agentRows = $this->paginateCollection($agentRows, $request);
        $brands = $salesBrands;
        $brandContextName = $brandId
            ? Brand::query()->whereKey($brandId)->value('imprint_name')
            : 'All Brands';
        $targetTeamRows = $targetTeams
            ->map(fn (Team $team) => [
                'id' => $team->id,
                'name' => $team->name,
                'brand' => $team->brand?->imprint_name,
                'target' => (float) ($teamTargets->get($team->id)?->amount ?? 0),
            ]);

        return view('reports.sales-performance', [
            'summary' => $summary,
            'agentRows' => $agentRows,
            'teamRows' => $teamRows,
            'targetTeamRows' => $targetTeamRows,
            'brands' => $brands,
            'brandContextName' => $brandContextName,
            'month' => $month,
            'brandId' => $brandId,
            'search' => $search,
            'canManageTargets' => $canManageTargets,
            'canViewAllCommissionNumbers' => $canViewAllRows,
        ]);
    }

    public function updateTargets(Request $request): RedirectResponse
    {
        abort_unless($this->canManageTargets($request->user()), 403);

        $request->merge([
            'team_targets' => collect($request->input('team_targets', []))
                ->map(fn ($value) => $this->normalizedAmount($value))
                ->all(),
        ]);

        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'team_targets' => ['nullable', 'array'],
            'team_targets.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $month = Carbon::createFromFormat('!Y-m', $validated['month'])->startOfMonth();
        $brandId = BrandScope::canAccessAllBrands($request->user())
            ? ($validated['brand_id'] ?? null)
            : BrandScope::userBrandId($request->user());

        if ($brandId && BrandScope::canAccessAllBrands($request->user())) {
            abort_unless(Brand::query()->whereKey($brandId)->where('is_sales_brand', true)->exists(), 422);
        }

        $teamTargets = collect($validated['team_targets'] ?? [])
            ->mapWithKeys(fn ($amount, $teamId) => [(int) $teamId => (float) $amount]);

        if ($teamTargets->isNotEmpty()) {
            $allowedTeamIds = Team::query()
                ->whereIn('id', $teamTargets->keys())
                ->where('department', 'Sales')
                ->where('show_on_sales_dashboard', true)
                ->whereHas('brand', fn ($query) => $query->where('is_sales_brand', true))
                ->when($brandId, fn ($query) => $query->where('brand_id', $brandId))
                ->when(! $brandId && ! BrandScope::canAccessAllBrands($request->user()), fn ($query) => BrandScope::apply($query, $request->user()))
                ->pluck('id');

            foreach ($teamTargets->only($allowedTeamIds->all()) as $teamId => $amount) {
                $team = Team::query()->find($teamId);

                SalesTarget::updateOrCreate(
                    [
                        'brand_id' => $team?->brand_id,
                        'target_month' => $month->toDateString(),
                        'target_type' => 'team',
                        'user_id' => null,
                        'team_id' => $teamId,
                    ],
                    [
                        'amount' => $amount,
                    ]
                );
            }
        }

        return redirect()
            ->route('reports.sales-performance.index', array_filter([
                'month' => $month->format('Y-m'),
                'brand_id' => $brandId,
            ], fn ($value) => $value !== null && $value !== ''))
            ->with('status', 'Sales targets updated successfully.');
    }

    private function canManageTargets(?User $user): bool
    {
        return $user?->role?->name === 'Admin' || (bool) $user?->hasPermission('manage_sales_targets');
    }

    private function normalizedAmount(mixed $value): mixed
    {
        return is_string($value) ? str_replace(',', '', $value) : $value;
    }

    private function monthFromRequest(Request $request): Carbon
    {
        $month = (string) $request->query('month', now()->format('Y-m'));

        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = now()->format('Y-m');
        }

        return Carbon::createFromFormat('!Y-m', $month)->startOfMonth();
    }

    private function paginateCollection(Collection $rows, Request $request): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = \App\Models\AppSetting::leadsSalesRecordsPerPage();

        return new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }
}
