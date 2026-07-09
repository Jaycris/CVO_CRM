<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\SalesTarget;
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
        $canViewAllRows = $user?->role?->name === 'Admin'
            || $user?->hasPermission('view_sales_performance_mtd')
            || $canManageTargets;

        abort_unless($canViewAllRows || $user?->department === 'Sales', 403);

        $month = $this->monthFromRequest($request);
        $brandId = BrandScope::canAccessAllBrands($user) ? $request->integer('brand_id') ?: null : BrandScope::userBrandId($user);
        $search = trim((string) $request->query('search', ''));
        $summary = SalesMtdCalculator::summary($user, $month, $brandId);

        $agentsQuery = User::query()
            ->with(['brand', 'role'])
            ->where('department', 'Sales')
            ->when($brandId, fn ($query) => $query->where('brand_id', $brandId))
            ->when(! $brandId, fn ($query) => BrandScope::apply($query, $user))
            ->when(! $canViewAllRows, fn ($query) => $query->where('id', $user?->id))
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
                'service_commission' => 0,
                'markup_commission' => 0,
                'usd_total' => 0,
            ]);
            $mtd = (float) $credit['mtd'];
            $targetAmount = (float) ($target?->amount ?? 0);

            return [
                'id' => $agent->id,
                'agent' => $agent,
                'work_setup' => $target?->work_setup,
                'mtd' => $mtd,
                'service_mtd' => (float) $credit['service_mtd'],
                'markup_mtd' => (float) $credit['markup_mtd'],
                'service_commission' => (float) $credit['service_commission'],
                'markup_commission' => (float) $credit['markup_commission'],
                'usd_total' => (float) $credit['usd_total'],
                'service_commission_percent' => (float) ($agent->service_commission_percent ?? 20),
                'markup_commission_percent' => (float) ($agent->markup_commission_percent ?? 50),
                'target' => $targetAmount,
                'remaining' => max($targetAmount - $mtd, 0),
                'percent' => $targetAmount > 0 ? round(($mtd / $targetAmount) * 100, 2) : 0,
            ];
        });

        $agentRows = $this->paginateCollection($agentRows, $request);
        $brands = BrandScope::canAccessAllBrands($user) ? Brand::orderBy('imprint_name')->get() : collect();

        return view('reports.sales-performance', [
            'summary' => $summary,
            'agentRows' => $agentRows,
            'brands' => $brands,
            'month' => $month,
            'brandId' => $brandId,
            'search' => $search,
            'canManageTargets' => $canManageTargets,
        ]);
    }

    public function updateTargets(Request $request): RedirectResponse
    {
        abort_unless($this->canManageTargets($request->user()), 403);

        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'global_target' => ['nullable', 'numeric', 'min:0'],
            'remote_target' => ['nullable', 'numeric', 'min:0'],
            'site_target' => ['nullable', 'numeric', 'min:0'],
            'agents' => ['nullable', 'array'],
            'agents.*.id' => ['required', 'exists:users,id'],
            'agents.*.work_setup' => ['nullable', 'in:remote,site'],
            'agents.*.target' => ['nullable', 'numeric', 'min:0'],
            'agents.*.service_commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'agents.*.markup_commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $month = Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth();
        $brandId = BrandScope::canAccessAllBrands($request->user())
            ? ($validated['brand_id'] ?? BrandScope::userBrandId($request->user()))
            : BrandScope::userBrandId($request->user());

        foreach ([
            'global' => $validated['global_target'] ?? 0,
            'remote' => $validated['remote_target'] ?? 0,
            'site' => $validated['site_target'] ?? 0,
        ] as $type => $amount) {
            SalesTarget::updateOrCreate(
                [
                    'brand_id' => $brandId,
                    'target_month' => $month->toDateString(),
                    'target_type' => $type,
                    'user_id' => null,
                ],
                [
                    'amount' => $amount,
                    'work_setup' => null,
                ]
            );

            User::query()
                ->whereKey($agentPayload['id'])
                ->update([
                    'service_commission_percent' => $agentPayload['service_commission_percent'] ?? 20,
                    'markup_commission_percent' => $agentPayload['markup_commission_percent'] ?? 50,
                ]);
        }

        foreach ($validated['agents'] ?? [] as $agentPayload) {
            SalesTarget::updateOrCreate(
                [
                    'brand_id' => $brandId,
                    'target_month' => $month->toDateString(),
                    'target_type' => 'agent',
                    'user_id' => $agentPayload['id'],
                ],
                [
                    'amount' => $agentPayload['target'] ?? 0,
                    'work_setup' => $agentPayload['work_setup'] ?: null,
                ]
            );
        }

        return redirect()
            ->route('reports.sales-performance.index', [
                'month' => $month->format('Y-m'),
                'brand_id' => $brandId,
            ])
            ->with('status', 'Sales targets updated successfully.');
    }

    private function canManageTargets(?User $user): bool
    {
        return $user?->role?->name === 'Admin' || (bool) $user?->hasPermission('manage_sales_targets');
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
        $perPage = 10;

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
