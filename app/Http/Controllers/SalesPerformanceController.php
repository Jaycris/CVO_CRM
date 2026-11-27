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
use Illuminate\Support\Facades\Schema;
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
        $canUseSalesBrandFilter = $isAdmin || $user->multiple_brands_enabled
            || (BrandScope::canAccessAllBrands($user)
                && ($user?->hasPermission('view_all_agent_mtd_directory') || $canManageTargets));
        $salesBrands = $canUseSalesBrandFilter
            ? Brand::query()->where('is_sales_brand', true)->tap(fn ($query) => BrandScope::apply($query, $user, 'id'))->orderBy('imprint_name')->get()
            : collect();
        $requestedBrandId = $request->integer('brand_id') ?: null;
        $brandId = $canUseSalesBrandFilter
            ? ($salesBrands->contains('id', $requestedBrandId) ? $requestedBrandId : null)
            : BrandScope::userBrandId($user);
        $search = trim((string) $request->query('search', ''));
        $includeOwnCreditsAcrossBrands = ! BrandScope::canAccessAllBrands($user)
            && $user?->department === 'Sales'
            && ! $brandId;
        $summaryUser = BrandScope::canAccessAllBrands($user) ? null : $user;
        $summary = SalesMtdCalculator::summary($summaryUser, $month, $brandId, $includeOwnCreditsAcrossBrands, $canUseSalesBrandFilter && ! $brandId);
        $hasTeamTargets = Schema::hasColumn('sales_targets', 'team_id');
        $teamTargets = collect();
        $targetTeams = collect();

        if ($hasTeamTargets) {
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
                ->when(! $brandId, fn ($query) => BrandScope::apply($query, $user))
                ->get();
            $teamTargets = SalesMtdCalculator::dashboardTeamTargets($teamTargets);
            $targetTeams = Team::query()
                ->with(['brand', 'accessibleBrands', 'members' => fn ($query) => $query
                    ->where('department', 'Sales')
                    ->where('is_commission_eligible', true)
                    ->select(['id', 'team_id', 'first_name', 'last_name', 'brand_id', 'multiple_brands_enabled'])
                    ->with('accessibleBrands')])
                ->forSalesDashboard()
                ->when($brandId, fn ($query) => BrandScope::applyToTeams($query, [$brandId]))
                ->when(! $brandId, fn ($query) => BrandScope::apply($query, $user))
                ->orderBy('name')
                ->get();
        }
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
            ->when($brandId, fn ($query) => BrandScope::applyToUsers($query, [$brandId]))
            ->when($canUseSalesBrandFilter && ! $brandId, fn ($query) => $query->whereHas('brand', fn ($query) => $query->where('is_sales_brand', true)))
            ->when(! $brandId, fn ($query) => BrandScope::apply($query, $user))
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
                'gross_mtd' => (float) ($credit['gross_mtd'] ?? $mtd),
                'mtd_deduction' => (float) ($credit['mtd_deduction'] ?? 0),
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
                return $row['is_commission_eligible'] || $row['mtd'] != 0 || $row['mtd_deduction'] > 0;
            }

            return $row['mtd'] != 0 || $row['mtd_deduction'] > 0 || (int) $row['id'] === (int) $user?->id;
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

        $targetTeamRows = $targetTeams->flatMap(function (Team $team) use ($brandId, $user, $teamTargets) {
            $ids = $team->eligibleBrandIds();
            return Brand::whereIn('id', $ids)->where('is_sales_brand', true)
                ->when($brandId, fn ($query) => $query->whereKey($brandId))
                ->tap(fn ($query) => BrandScope::apply($query, $user, 'id'))->orderBy('imprint_name')->get()
                ->map(fn ($brand) => [
                'id' => $team->id,
                'brand_id' => $brand->id,
                'name' => $team->name,
                'brand' => $brand->imprint_name,
                'target' => (float) $teamTargets->where('team_id', $team->id)->filter(fn ($target) => (int) ($target->brand_id ?? $team->brand_id) === (int) $brand->id)->sum('amount'),
            ]);
        });

        $teamsById = $targetTeams->keyBy('id');
        $teamRows = $targetTeamRows->map(function (array $row) use ($teamsById, $summary) {
            $team = $teamsById->get($row['id']);
            $mtd = SalesMtdCalculator::teamSalesMtd($team, $summary, $row['brand_id']);
            return [
                'id' => $team->id,
                'brand_id' => $row['brand_id'],
                'name' => $team->name.($team->multiple_brands_enabled ? ' — '.$row['brand'] : ''),
                'team_name' => $team->name,
                'brand' => $row['brand'],
                'mtd' => $mtd, 'target' => $row['target'],
                'members' => $team->members->filter(fn (User $member) => in_array((int) $row['brand_id'], BrandScope::allowedBrandIds($member), true))->count(),
                'remaining' => max($row['target'] - $mtd, 0),
                'percent' => $row['target'] > 0 ? round($mtd / $row['target'] * 100, 2) : 0,
            ];
        })->sortByDesc('mtd')->values();

        $agentRows = $this->paginateCollection($agentRows, $request);
        $brands = $salesBrands;
        $brandContextName = $brandId
            ? Brand::query()->whereKey($brandId)->value('imprint_name')
            : 'All Brands';
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

        if (! Schema::hasColumn('sales_targets', 'team_id')) {
            return back()->with('status', 'Please run the latest migrations before updating team targets.');
        }

        $request->merge([
            'team_targets' => collect($request->input('team_targets', []))
                ->map(fn ($value) => $this->normalizedAmount($value))
                ->all(),
            'team_brand_targets' => is_array($request->input('team_brand_targets', []))
                ? collect($request->input('team_brand_targets', []))->map(fn ($values) => is_array($values)
                    ? collect($values)->map(fn ($value) => $this->normalizedAmount($value))->all() : $values)->all()
                : $request->input('team_brand_targets'),
        ]);

        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'team_targets' => ['nullable', 'array'],
            'team_targets.*' => ['nullable', 'numeric', 'min:0'],
            'team_brand_targets' => ['nullable', 'array'],
            'team_brand_targets.*' => ['array'],
            'team_brand_targets.*.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $month = Carbon::createFromFormat('!Y-m', $validated['month'])->startOfMonth();
        abort_if(! empty($validated['brand_id']) && ! BrandScope::canAccessBrand($request->user(), (int) $validated['brand_id']), 403);
        $brandId = (BrandScope::canAccessAllBrands($request->user()) || $request->user()->multiple_brands_enabled)
            ? ($validated['brand_id'] ?? null)
            : BrandScope::userBrandId($request->user());

        if ($brandId) {
            abort_unless(BrandScope::canAccessBrand($request->user(), $brandId)
                && Brand::query()->whereKey($brandId)->where('is_sales_brand', true)->exists(), 403);
        }

        $teamTargets = collect($validated['team_targets'] ?? [])
            ->mapWithKeys(fn ($amount, $teamId) => [(int) $teamId => (float) $amount]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $teamTargets, $month, $brandId, $request) {
            $nested = $validated['team_brand_targets'] ?? [];
            $ids = $teamTargets->keys()->merge(array_keys($nested))->unique();
            $teams = Team::with('accessibleBrands')->whereIn('id', $ids)->forSalesDashboard()->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $pairs = [];
            foreach ($teamTargets as $teamId => $amount) {
                $id = $brandId ?: $teams->get($teamId)?->brand_id;
                $pairs[$teamId][$id] = $amount;
            }
            foreach ($nested as $teamId => $values) {
                foreach ($values as $id => $amount) {
                    if (isset($pairs[$teamId][$id]) && (float) $pairs[$teamId][$id] !== (float) $amount) {
                        throw \Illuminate\Validation\ValidationException::withMessages(['team_brand_targets' => 'Conflicting targets were submitted for the same team and brand.']);
                    }
                    $pairs[$teamId][$id] = (float) $amount;
                }
            }
            $salesIds = Brand::where('is_sales_brand', true)->pluck('id')->all();
            foreach ($pairs as $teamId => $values) {
                $team = $teams->get($teamId);
                foreach ($values as $id => $amount) {
                    if (! $team || ! in_array((int) $id, $salesIds, true) || ! in_array((int) $id, $team->eligibleBrandIds(), true)
                        || ! BrandScope::canAccessBrand($request->user(), (int) $id) || ($brandId && (int) $id !== (int) $brandId)) {
                        throw \Illuminate\Validation\ValidationException::withMessages(['team_brand_targets' => 'Choose an eligible sales team and one of its permitted brands. No targets were changed.']);
                    }
                    // Date casts may persist a midnight timestamp in SQLite. Match the
                    // calendar date explicitly so repeated saves update the same row.
                    $existing = SalesTarget::where('brand_id', $id)->whereDate('target_month', $month->toDateString())
                        ->where('target_type', 'team')->whereNull('user_id')->where('team_id', $teamId)->latest('id')->first();
                    if ($existing) {
                        $existing->update(['amount' => $amount]);
                    } else {
                        SalesTarget::create(['brand_id' => $id, 'target_month' => $month->toDateString(),
                            'target_type' => 'team', 'user_id' => null, 'team_id' => $teamId, 'amount' => $amount]);
                    }
                }
            }
        });

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
