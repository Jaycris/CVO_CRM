<?php

namespace App\Http\Controllers;

use App\Models\SalesPayment;
use App\Support\BrandScope;
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

        $payments = SalesPayment::with([
                'brand',
                'endorsement.agent',
                'endorsement.brand',
                'endorsement.lead.createdBy',
                'endorsement.lead.verifiedBy',
            ])
            ->where('status', 'Payment Success')
            ->whereHas('endorsement.lead')
            ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
            ->when(! $this->userCanSeeAllCredit($request), function ($query) use ($request, $creditType) {
                $column = $creditType === 'verified' ? 'verified_by' : 'created_by';

                $query->whereHas('endorsement.lead', fn ($query) => $query->where($column, $request->user()->id));
            })
            ->when($search !== '', function ($query) use ($search) {
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
            })
            ->latest('sold_date')
            ->paginate(10)
            ->withQueryString();

        $summaryCards = [
            [
                'label' => $creditType === 'verified' ? 'Verified Sold Leads' : 'Sold Leads',
                'count' => $payments->total(),
                'hint' => 'Successful payments',
                'tone' => 'emerald',
            ],
        ];

        return view('leads.sales-credit', compact(
            'payments',
            'pageTitle',
            'pageDescription',
            'summaryCards',
            'search',
            'creditType'
        ));
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
