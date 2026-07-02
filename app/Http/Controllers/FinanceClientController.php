<?php

namespace App\Http\Controllers;

use App\Models\SalesPayment;
use App\Support\BrandScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinanceClientController extends Controller
{
    public function sold(Request $request): View
    {
        $this->ensureCanViewFinanceClients($request);

        $search = trim((string) $request->query('search', ''));
        $payments = $this->paymentQuery(['Payment Success'], $search, $request);

        return view('finance.clients', [
            'payments' => $payments,
            'pageTitle' => 'Sold Clients',
            'pageDescription' => 'Clients with successful payment records and active services in the company.',
            'emptyMessage' => 'No sold clients yet.',
            'search' => $search,
            'canDeleteClients' => $this->canDeleteFinanceRecords($request),
        ]);
    }

    public function refundsDisputes(Request $request): View
    {
        $this->ensureCanViewFinanceClients($request);

        $search = trim((string) $request->query('search', ''));
        $payments = $this->paymentQuery(['Refund', 'Dispute'], $search, $request);

        return view('finance.clients', [
            'payments' => $payments,
            'pageTitle' => 'Refunds & Disputes',
            'pageDescription' => 'Clients with refund or dispute records separated from paid clients.',
            'emptyMessage' => 'No refund or dispute records yet.',
            'search' => $search,
            'canDeleteClients' => $this->canDeleteFinanceRecords($request),
        ]);
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        abort_unless($this->canDeleteFinanceRecords($request), 403);

        $validated = $request->validate([
            'payment_ids' => ['required', 'array', 'min:1'],
            'payment_ids.*' => ['integer', 'exists:sales_payments,id'],
        ]);

        SalesPayment::whereIn('id', $validated['payment_ids'])
            ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
            ->delete();

        return back()->with('success', 'Selected client record(s) deleted successfully.');
    }

    private function paymentQuery(array $statuses, string $search, Request $request)
    {
        return SalesPayment::with(['brand', 'endorsement.agent', 'endorsement.brand'])
            ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
            ->whereIn('status', $statuses)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('payment_method', 'like', "%{$search}%")
                        ->orWhere('sold_date', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhereHas('endorsement', function ($query) use ($search) {
                            $query->where('author_name', 'like', "%{$search}%")
                                ->orWhere('book_title', 'like', "%{$search}%")
                                ->orWhere('services', 'like', "%{$search}%")
                                ->orWhere('amount', 'like', "%{$search}%")
                                ->orWhereHas('agent', function ($query) use ($search) {
                                    $query->where('first_name', 'like', "%{$search}%")
                                        ->orWhere('last_name', 'like', "%{$search}%");
                                });
                        });
                });
            })
            ->latest('sold_date')
            ->paginate(10)
            ->withQueryString();
    }

    private function ensureCanViewFinanceClients(Request $request): void
    {
        abort_unless(
            $request->user()?->role?->name === 'Admin'
            || (bool) $request->user()?->hasPermission('view_finance_clients'),
            403
        );
    }

    private function canDeleteFinanceRecords(Request $request): bool
    {
        return $request->user()?->role?->name === 'Admin'
            || (bool) $request->user()?->hasPermission('delete_payment_records');
    }
}
