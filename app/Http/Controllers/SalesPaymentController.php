<?php

namespace App\Http\Controllers;

use App\Models\SalesEndorsement;
use App\Models\AppSetting;
use App\Models\SalesActivity;
use App\Models\SalesPayment;
use App\Notifications\LeadSaleCreditNotification;
use App\Notifications\PaymentStatusNotification;
use App\Support\BrandScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesPaymentController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($this->userHasPermission($request, 'view_payment_records'), 403);

        $search = trim((string) $request->query('search', ''));

        $payments = SalesPayment::with(['endorsement.agent', 'endorsement.brand', 'brand'])
            ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('payment_method', 'like', "%{$search}%")
                        ->orWhere('sold_date', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhereHas('endorsement', function ($query) use ($search) {
                            $query->where('endorsement_code', 'like', "%{$search}%")
                                ->orWhere('author_name', 'like', "%{$search}%")
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
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('sales-payments.index', [
            'payments' => $payments,
            'endorsements' => SalesEndorsement::with('agent')
                ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
                ->whereDoesntHave('paymentRecord')
                ->latest()
                ->get(),
            'paymentMethods' => $this->paymentMethods(),
            'statuses' => $this->statuses(),
            'canManagePayments' => $this->userHasPermission($request, 'manage_payment_records'),
            'canDeletePayments' => $this->userHasPermission($request, 'delete_payment_records'),
            'search' => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->userHasPermission($request, 'manage_payment_records'), 403);

        $validated = $request->validate([
            'sales_endorsement_id' => ['required', 'exists:sales_endorsements,id'],
            'payment_method' => ['required', 'in:Wire Payment,Invoice,Check Payment,Card'],
            'sold_date' => ['required', 'date'],
            'status' => ['required', 'in:Payment Success,Processing,Declined,Refund,Dispute'],
        ]);

        $endorsement = SalesEndorsement::findOrFail($validated['sales_endorsement_id']);
        abort_unless($this->userCanAccessBrand($request, $endorsement->brand_id), 403);

        $payment = SalesPayment::withTrashed()
            ->firstOrNew(['sales_endorsement_id' => $validated['sales_endorsement_id']]);
        $previousStatus = $payment->exists ? $payment->status : null;

        if ($payment->exists && $payment->trashed()) {
            $payment->restore();
        }

        $payment->fill(collect($validated)->except('sales_endorsement_id')->all());
        $payment->brand_id = $endorsement->brand_id ?? BrandScope::userBrandId($request->user());
        $payment->sales_endorsement_id = $validated['sales_endorsement_id'];
        $payment->save();
        $payment->load('endorsement.agent', 'endorsement.frankieAgent', 'endorsement.service', 'endorsement.lead.createdBy', 'endorsement.lead.verifiedBy');

        if ($this->shouldNotifyLeadCreditForStatus($validated['status']) && $previousStatus !== $validated['status']) {
            $this->notifyLeadSaleCreditUsers($payment);
        }

        $this->syncSalesActivity($payment);

        $this->notifySalesAgentPaymentStatus($payment);

        return redirect()
            ->route('finance.payments.index')
            ->with('success', 'Payment record added successfully.');
    }

    public function update(Request $request, SalesPayment $payment): RedirectResponse
    {
        abort_unless($this->userHasPermission($request, 'manage_payment_records'), 403);
        abort_unless($this->userCanAccessBrand($request, $payment->brand_id), 403);

        $validated = $request->validate([
            'payment_method' => ['required', 'in:Wire Payment,Invoice,Check Payment,Card'],
            'sold_date' => ['required', 'date'],
            'status' => ['required', 'in:Payment Success,Processing,Declined,Refund,Dispute'],
        ]);

        $previousStatus = $payment->status;
        $payment->update($validated);

        if ($this->shouldNotifyLeadCreditForStatus($validated['status']) && $previousStatus !== $validated['status']) {
            $payment->loadMissing('endorsement.agent', 'endorsement.frankieAgent', 'endorsement.service', 'endorsement.lead.createdBy', 'endorsement.lead.verifiedBy');
            $this->notifyLeadSaleCreditUsers($payment);
        }

        $payment->loadMissing('endorsement.agent', 'endorsement.frankieAgent', 'endorsement.service', 'endorsement.lead.createdBy', 'endorsement.lead.verifiedBy');
        $this->syncSalesActivity($payment);

        if ($validated['status'] !== $previousStatus) {
            $this->notifySalesAgentPaymentStatus($payment);
        }

        return redirect()
            ->route('finance.payments.index')
            ->with('success', 'Payment record updated successfully.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        abort_unless($this->userHasPermission($request, 'delete_payment_records'), 403);

        $validated = $request->validate([
            'payment_ids' => ['required', 'array', 'min:1'],
            'payment_ids.*' => ['integer', 'exists:sales_payments,id'],
        ]);

        SalesPayment::whereIn('id', $validated['payment_ids'])
            ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
            ->delete();

        return back()->with('success', 'Selected payment record(s) deleted successfully.');
    }

    private function userHasPermission(Request $request, string $permission): bool
    {
        return $request->user()?->role?->name === 'Admin'
            || (bool) $request->user()?->hasPermission($permission);
    }

    private function userCanReceiveNotification($user, string $permission): bool
    {
        return $user->role?->name === 'Admin'
            || (bool) $user->hasPermission($permission);
    }

    private function notifyLeadSaleCreditUsers(SalesPayment $payment): void
    {
        $lead = $payment->endorsement?->lead;

        if (! $lead) {
            return;
        }

        if ($lead->createdBy && $this->userCanReceiveNotification($lead->createdBy, 'view_sold_mined_leads')) {
            $this->notifyGroupedLeadSaleCredit($lead->createdBy, 'mined', $payment);
        }

        if ($lead->verifiedBy && $this->userCanReceiveNotification($lead->verifiedBy, 'view_verified_sold_leads')) {
            $this->notifyGroupedLeadSaleCredit($lead->verifiedBy, 'verified', $payment);
        }
    }

    private function notifySalesAgentPaymentStatus(SalesPayment $payment): void
    {
        $payment->loadMissing('endorsement.agent');
        $endorsement = $payment->endorsement;

        if (! $endorsement?->agent || ! $this->userCanReceiveNotification($endorsement->agent, 'view_sales')) {
            return;
        }

        $endorsement->agent->notify(new PaymentStatusNotification($endorsement, $payment));
    }

    private function notifyGroupedLeadSaleCredit($user, string $creditType, SalesPayment $payment): void
    {
        $status = $payment->status;
        $existingNotification = $user->unreadNotifications()
            ->where('type', LeadSaleCreditNotification::class)
            ->get()
            ->first(fn ($notification) => ($notification->data['credit_type'] ?? null) === $creditType
                && ($notification->data['payment_status'] ?? null) === $status);

        if (! $existingNotification) {
            $user->notify(new LeadSaleCreditNotification($creditType, $payment));

            return;
        }

        $count = (int) ($existingNotification->data['count'] ?? 1) + 1;
        $existingNotification->forceFill([
            'data' => (new LeadSaleCreditNotification($creditType, $payment, $count))->toArray($user),
        ])->save();
    }

    private function shouldNotifyLeadCreditForStatus(string $status): bool
    {
        return in_array($status, ['Payment Success', 'Refund', 'Dispute'], true);
    }

    private function syncSalesActivity(SalesPayment $payment): void
    {
        $endorsement = $payment->endorsement;

        if (! $endorsement) {
            return;
        }

        $existingActivity = SalesActivity::where('sales_payment_id', $payment->id)->first();

        if ($payment->status !== 'Payment Success' && ! $existingActivity) {
            return;
        }

        $lead = $endorsement->lead;
        $amount = (float) ($endorsement->amount ?? 0);
        $frankiePercent = ($endorsement->has_frankie && $endorsement->frankie_agent_id)
            ? (float) ($endorsement->frankie_commission_percent ?? AppSetting::get('frankie_commission_percent', 50))
            : 0.0;
        $frankieCredit = round($amount * ($frankiePercent / 100), 2);
        $agentCredit = round($amount - $frankieCredit, 2);

        SalesActivity::updateOrCreate(
            ['sales_payment_id' => $payment->id],
            [
                'brand_id' => $payment->brand_id ?? $endorsement->brand_id,
                'sales_endorsement_id' => $endorsement->id,
                'lead_id' => $endorsement->lead_id,
                'agent_id' => $endorsement->agent_id,
                'frankie_agent_id' => $endorsement->frankie_agent_id,
                'lead_miner_id' => $lead?->created_by,
                'verifier_id' => $lead?->verified_by,
                'service_id' => $endorsement->service_id,
                'activity_type' => 'payment_success',
                'endorsement_code' => $endorsement->endorsement_code,
                'author_name' => $endorsement->author_name,
                'book_title' => $endorsement->book_title,
                'service_name' => $endorsement->service?->name ?? $endorsement->services,
                'amount' => $amount,
                'agent_credit_amount' => $agentCredit,
                'frankie_credit_amount' => $frankieCredit,
                'frankie_commission_percent' => $frankiePercent,
                'payment_method' => $payment->payment_method,
                'payment_status' => $payment->status,
                'sold_date' => $payment->sold_date,
            ]
        );
    }

    private function userCanAccessBrand(Request $request, ?int $brandId): bool
    {
        return BrandScope::canAccessAllBrands($request->user())
            || (int) $request->user()?->brand_id === (int) $brandId;
    }

    private function paymentMethods(): array
    {
        return ['Wire Payment', 'Invoice', 'Check Payment', 'Card'];
    }

    private function statuses(): array
    {
        return ['Payment Success', 'Processing', 'Declined', 'Refund', 'Dispute'];
    }
}
