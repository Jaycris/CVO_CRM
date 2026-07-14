<?php

namespace App\Http\Controllers;

use App\Models\SalesPayment;
use App\Support\BrandScope;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeadSaleCreditController extends Controller
{
    public function soldMined(Request $request): View
    {
        abort_unless($this->userHasPermission($request, 'view_sold_mined_leads'), 403);

        return $this->index(
            request: $request,
            creditType: 'mined',
            pageTitle: 'Sold Leads',
            pageDescription: 'Successful sales with Lead Miner and Verifier stamps.'
        );
    }

    public function verifiedSold(Request $request): View
    {
        abort_unless(
            $request->user()?->role?->name === 'Verifier'
            && (bool) $request->user()?->hasPermission('view_verified_sold_leads'),
            403
        );

        return $this->index(
            request: $request,
            creditType: 'verified',
            pageTitle: 'Verified Sold Leads',
            pageDescription: 'Sales that came from leads verified by you or your accessible team.'
        );
    }

    private function index(Request $request, string $creditType, string $pageTitle, string $pageDescription): View
    {
        $search = trim((string) $request->query('search', ''));
        $canSeeFullDetails = $this->userCanSeeAllCredit($request);

        $paymentsQuery = SalesPayment::with([
                'brand',
                'endorsement.agent',
                'endorsement.brand',
                'endorsement.lead.createdBy',
                'endorsement.lead.verifiedBy',
            ])
            ->whereIn('status', ['Payment Success', 'Refund', 'Dispute'])
            ->whereHas('endorsement.lead')
            ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
            ->when(! $canSeeFullDetails, function ($query) use ($request, $creditType) {
                $column = $creditType === 'verified' ? 'verified_by' : 'created_by';

                $query->whereHas('endorsement.lead', fn ($query) => $query->where($column, $request->user()->id));
            })
            ->when($canSeeFullDetails && $search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('payment_method', 'like', "%{$search}%")
                        ->orWhere('sold_date', 'like', "%{$search}%")
                        ->orWhereHas('endorsement', function ($query) use ($search) {
                            $query->where('endorsement_code', 'like', "%{$search}%")
                                ->orWhere('author_name', 'like', "%{$search}%")
                                ->orWhere('book_title', 'like', "%{$search}%")
                                ->orWhere('services', 'like', "%{$search}%")
                                ->orWhere('amount', 'like', "%{$search}%")
                                ->orWhereHas('agent', function ($query) use ($search) {
                                    $query->where('first_name', 'like', "%{$search}%")
                                        ->orWhere('last_name', 'like', "%{$search}%");
                                })
                                ->orWhereHas('lead.createdBy', function ($query) use ($search) {
                                    $query->where('first_name', 'like', "%{$search}%")
                                        ->orWhere('last_name', 'like', "%{$search}%");
                                })
                                ->orWhereHas('lead.verifiedBy', function ($query) use ($search) {
                                    $query->where('first_name', 'like', "%{$search}%")
                                        ->orWhere('last_name', 'like', "%{$search}%");
                                });
                        });
                });
            });

        $creditSummaries = $this->creditSummaries((clone $paymentsQuery)->get());

        $payments = $canSeeFullDetails
            ? $paymentsQuery
            ->latest('sold_date')
            ->paginate(10)
                ->withQueryString()
            : null;

        $soldCount = (clone $paymentsQuery)->where('status', 'Payment Success')->count();
        $refundCount = (clone $paymentsQuery)->where('status', 'Refund')->count();
        $disputeCount = (clone $paymentsQuery)->where('status', 'Dispute')->count();

        $summaryCards = [
            [
                'label' => $creditType === 'verified' ? 'Verified Sold Leads' : 'Sold Leads',
                'count' => $soldCount,
                'hint' => 'Successful sold leads',
                'tone' => 'emerald',
            ],
            [
                'label' => 'Refunded Sold Leads',
                'count' => $refundCount,
                'hint' => 'Sold leads refunded',
                'tone' => 'rose',
            ],
            [
                'label' => 'Disputed Sold Leads',
                'count' => $disputeCount,
                'hint' => 'Sold leads disputed',
                'tone' => 'amber',
            ],
        ];

        return view('leads.sales-credit', compact(
            'payments',
            'pageTitle',
            'pageDescription',
            'summaryCards',
            'search',
            'creditType',
            'creditSummaries',
            'canSeeFullDetails'
        ));
    }

    private function creditSummaries(Collection $payments): Collection
    {
        return $payments
            ->groupBy(fn (SalesPayment $payment) => $payment->sold_date?->format('F Y') ?? 'No Sold Date')
            ->map(function (Collection $payments, string $month) {
                return [
                    'month' => $month,
                    'sold' => $payments->where('status', 'Payment Success')->count(),
                    'refund' => $payments->where('status', 'Refund')->count(),
                    'dispute' => $payments->where('status', 'Dispute')->count(),
                    'total' => $payments->count(),
                ];
            })
            ->values();
    }

    private function userHasPermission(Request $request, string $permission): bool
    {
        return $request->user()?->role?->name === 'Admin'
            || (bool) $request->user()?->hasPermission($permission);
    }

    private function userCanSeeAllCredit(Request $request): bool
    {
        return $request->user()?->role?->name === 'Admin';
    }
}
