<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Brand;
use App\Models\SalesActivity;
use App\Models\SalesTarget;
use App\Models\User;
use App\Support\BrandScope;
use App\Support\SalesMtdCalculator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AgentStatementController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $canViewAll = $user?->role?->name === 'Admin' || (bool) $user?->hasPermission('view_all_agent_statements');

        abort_unless(
            $canViewAll || $user?->department === 'Sales' || $user?->hasPermission('view_agent_statements'),
            403
        );

        $month = $this->monthFromRequest($request);
        $brandId = BrandScope::canAccessAllBrands($user)
            ? $request->integer('brand_id') ?: null
            : BrandScope::userBrandId($user);
        $agentId = $canViewAll ? ($request->integer('agent_id') ?: null) : $user?->id;
        $search = trim((string) $request->query('search', ''));

        $activities = SalesActivity::query()
            ->with(['brand', 'agent', 'frankieAgent', 'service'])
            ->where('payment_status', 'Payment Success')
            ->whereBetween('sold_date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->when($brandId, fn ($query) => $query->where('brand_id', $brandId))
            ->when(! $brandId, fn ($query) => BrandScope::apply($query, $user))
            ->when($agentId, function ($query) use ($agentId) {
                $query->where(function ($query) use ($agentId) {
                    $query->where('agent_id', $agentId)
                        ->orWhere('frankie_agent_id', $agentId);
                });
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('endorsement_code', 'like', "%{$search}%")
                        ->orWhere('author_name', 'like', "%{$search}%")
                        ->orWhere('book_title', 'like', "%{$search}%")
                        ->orWhere('service_name', 'like', "%{$search}%")
                        ->orWhere('payment_method', 'like', "%{$search}%")
                        ->orWhereHas('brand', fn ($query) => $query->where('imprint_name', 'like', "%{$search}%"))
                        ->orWhereHas('agent', fn ($query) => $this->searchUser($query, $search))
                        ->orWhereHas('frankieAgent', fn ($query) => $this->searchUser($query, $search));
                });
            })
            ->latest('sold_date')
            ->latest('id')
            ->get();

        $rows = SalesMtdCalculator::statementRows($activities)
            ->when($agentId, fn (Collection $rows, int $selectedAgentId) => $rows->where('agent_id', $selectedAgentId)->values())
            ->sortByDesc(fn (array $row) => $row['activity']->sold_date?->timestamp ?? 0)
            ->values();

        $targets = SalesTarget::query()
            ->whereDate('target_month', $month->toDateString())
            ->where('target_type', 'agent')
            ->when($brandId, fn ($query) => $query->where('brand_id', $brandId))
            ->when(! $brandId, fn ($query) => BrandScope::apply($query, $user))
            ->when($agentId, fn ($query) => $query->where('user_id', $agentId))
            ->when(! $agentId && $rows->isNotEmpty(), fn ($query) => $query->whereIn('user_id', $rows->pluck('agent_id')->filter()->unique()))
            ->get();

        $targetAmount = (float) $targets->sum('amount');
        $salesCredit = (float) $rows->sum('amount');
        $totals = [
            'sales_credit' => $salesCredit,
            'service_mtd' => (float) $rows->sum('service_amount'),
            'markup_mtd' => (float) $rows->sum('markup_amount'),
            'service_commission' => (float) $rows->sum('service_commission'),
            'markup_commission' => (float) $rows->sum('markup_commission'),
            'usd_total' => (float) $rows->sum('usd_total'),
            'target' => $targetAmount,
            'remaining' => max($targetAmount - $salesCredit, 0),
            'percent' => $targetAmount > 0 ? round(($salesCredit / $targetAmount) * 100, 2) : 0,
        ];

        $brands = BrandScope::canAccessAllBrands($user) ? Brand::orderBy('imprint_name')->get() : collect();
        $agents = $canViewAll
            ? User::query()
                ->with('brand')
                ->where('department', 'Sales')
                ->when($brandId, fn ($query) => $query->where('brand_id', $brandId))
                ->when(! $brandId, fn ($query) => BrandScope::apply($query, $user))
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get()
            : collect();

        return view('reports.agent-statements', [
            'rows' => $this->paginateCollection($rows, $request),
            'totals' => $totals,
            'brands' => $brands,
            'agents' => $agents,
            'month' => $month,
            'brandId' => $brandId,
            'agentId' => $agentId,
            'search' => $search,
            'canViewAll' => $canViewAll,
        ]);
    }

    private function searchUser($query, string $search): void
    {
        $query->where('first_name', 'like', "%{$search}%")
            ->orWhere('last_name', 'like', "%{$search}%")
            ->orWhere('email', 'like', "%{$search}%");
    }

    private function monthFromRequest(Request $request): Carbon
    {
        $month = (string) $request->query('month', now()->format('Y-m'));

        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = now()->format('Y-m');
        }

        return Carbon::createFromFormat('Y-m', $month)->startOfMonth();
    }

    private function paginateCollection(Collection $rows, Request $request): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = AppSetting::recordsPerPage();

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
