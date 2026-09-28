<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Brand;
use App\Models\SalesEndorsement;
use App\Support\BrandScope;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AuthorBalanceController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($this->canViewAuthorBalances($request), 403);

        $user = $request->user();
        $canViewAll = $this->canViewAllAuthorBalances($request);
        $tab = in_array($request->query('tab'), ['pending', 'paid'], true)
            ? $request->query('tab')
            : 'pending';
        $brandId = $canViewAll ? ($request->integer('brand_id') ?: null) : null;
        $search = trim((string) $request->query('search', ''));

        $rows = SalesEndorsement::query()
            ->with([
                'agent',
                'brand',
                'paymentRecords' => fn ($query) => $query->where('status', 'Payment Success'),
            ])
            ->when($canViewAll, fn ($query) => BrandScope::apply($query, $user))
            ->when(! $canViewAll, fn ($query) => $query->where('agent_id', $user?->id))
            ->when($brandId, fn ($query) => $query->where('brand_id', $brandId))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('endorsement_code', 'like', "%{$search}%")
                        ->orWhere('author_name', 'like', "%{$search}%")
                        ->orWhere('book_title', 'like', "%{$search}%")
                        ->orWhere('services', 'like', "%{$search}%")
                        ->orWhereHas('agent', fn ($query) => $this->searchUser($query, $search))
                        ->orWhereHas('brand', fn ($query) => $query->where('imprint_name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->get()
            ->map(fn (SalesEndorsement $endorsement) => $this->balanceRow($endorsement));

        $summaryRows = $rows;
        $rows = $rows
            ->filter(fn (array $row) => $tab === 'paid'
                ? $row['remaining_balance'] <= 0
                : $row['remaining_balance'] > 0)
            ->values();

        $brands = $canViewAll
            ? Brand::query()
                ->where(function ($query) {
                    $query->whereHas('users')
                        ->orWhereIn('id', SalesEndorsement::query()->select('brand_id')->whereNotNull('brand_id'));
                })
                ->when(! BrandScope::canAccessAllBrands($user), fn ($query) => $query->whereKey($user?->brand_id))
                ->orderBy('imprint_name')
                ->get()
            : collect();

        return view('finance.author-balances', [
            'rows' => $this->paginateCollection($rows, $request),
            'summary' => [
                'pending_count' => $summaryRows->where('remaining_balance', '>', 0)->count(),
                'paid_count' => $summaryRows->where('remaining_balance', '<=', 0)->count(),
                'contract_total' => (float) $summaryRows->sum('contract_amount'),
                'paid_total' => (float) $summaryRows->sum('paid_amount'),
                'balance_total' => (float) $summaryRows->sum('remaining_balance'),
            ],
            'brands' => $brands,
            'tab' => $tab,
            'brandId' => $brandId,
            'search' => $search,
            'canViewAll' => $canViewAll,
        ]);
    }

    private function canViewAuthorBalances(Request $request): bool
    {
        $user = $request->user();

        return $this->canViewAllAuthorBalances($request)
            || ($user?->department === 'Sales' && (bool) $user?->is_commission_eligible)
            || (bool) $user?->hasPermission('submit_sales_endorsement')
            || (bool) $user?->hasPermission('view_own_sales_endorsements');
    }

    private function canViewAllAuthorBalances(Request $request): bool
    {
        $user = $request->user();

        return $user?->role?->name === 'Admin'
            || $user?->role?->name === 'Finance Officer'
            || (bool) $user?->hasPermission('view_payment_records')
            || (bool) $user?->hasPermission('view_finance_clients')
            || (bool) $user?->hasPermission('view_contract_records');
    }

    private function balanceRow(SalesEndorsement $endorsement): array
    {
        $contractAmount = (float) ($endorsement->amount_to_be_paid ?? $endorsement->amount ?? 0);
        $paidAmount = (float) $endorsement->paymentRecords->sum('amount');
        $remainingBalance = max($contractAmount - $paidAmount, 0);

        return [
            'endorsement' => $endorsement,
            'contract_amount' => $contractAmount,
            'paid_amount' => $paidAmount,
            'remaining_balance' => $remainingBalance,
            'paid_percent' => $contractAmount > 0 ? min(round(($paidAmount / $contractAmount) * 100, 2), 100) : 0,
        ];
    }

    private function searchUser($query, string $search): void
    {
        $query->where('first_name', 'like', "%{$search}%")
            ->orWhere('last_name', 'like', "%{$search}%")
            ->orWhere('email', 'like', "%{$search}%");
    }

    private function paginateCollection(Collection $rows, Request $request): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = AppSetting::leadsSalesRecordsPerPage();

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
