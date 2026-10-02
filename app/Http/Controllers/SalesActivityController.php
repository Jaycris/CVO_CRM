<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\SalesActivity;
use App\Models\User;
use App\Support\BrandScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesActivityController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(
            $request->user()?->role?->name === 'Admin'
            || (bool) $request->user()?->hasPermission('view_sales_activity'),
            403
        );

        $search = trim((string) $request->query('search', ''));
        $canManageChargebacks = $this->canManageChargebacks($request);

        $activities = SalesActivity::with(['brand', 'agent', 'frankieAgent', 'leadMiner', 'verifier', 'service'])
            ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('endorsement_code', 'like', "%{$search}%")
                        ->orWhere('author_name', 'like', "%{$search}%")
                        ->orWhere('book_title', 'like', "%{$search}%")
                        ->orWhere('service_name', 'like', "%{$search}%")
                        ->orWhere('activity_type', 'like', "%{$search}%")
                        ->orWhere('payment_status', 'like', "%{$search}%")
                        ->orWhereHas('brand', fn ($query) => $query->where('imprint_name', 'like', "%{$search}%"))
                        ->orWhereHas('agent', function ($query) use ($search) {
                            $query->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('frankieAgent', function ($query) use ($search) {
                            $query->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('leadMiner', function ($query) use ($search) {
                            $query->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('verifier', function ($query) use ($search) {
                            $query->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                });
            })
            ->latest('sold_date')
            ->latest()
            ->paginate(\App\Models\AppSetting::leadsSalesRecordsPerPage())
            ->withQueryString();

        return view('sales-activities.index', [
            'activities' => $activities,
            'search' => $search,
            'canManageChargebacks' => $canManageChargebacks,
            'brands' => $canManageChargebacks
                ? BrandScope::apply(Brand::query(), $request->user())->where('is_sales_brand', true)->orderBy('imprint_name')->get()
                : collect(),
            'agents' => $canManageChargebacks
                ? BrandScope::apply(User::query()->with('brand'), $request->user())
                    ->where('department', 'Sales')
                    ->where('is_commission_eligible', true)
                    ->orderBy('first_name')
                    ->orderBy('last_name')
                    ->get(['id', 'brand_id', 'first_name', 'last_name'])
                : collect(),
        ]);
    }

    public function storeChargeback(Request $request): RedirectResponse
    {
        abort_unless($this->canManageChargebacks($request), 403);

        $validated = $request->validate([
            'brand_id' => ['required', 'exists:brands,id'],
            'agent_id' => ['required', 'exists:users,id'],
            'endorsement_code' => ['nullable', 'string', 'max:255'],
            'author_name' => ['required', 'string', 'max:255'],
            'book_title' => ['nullable', 'string', 'max:255'],
            'service_name' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', 'in:Wire Payment,Invoice,Check Payment,Card'],
            'original_sold_date' => ['nullable', 'date'],
            'chargeback_date' => ['required', 'date'],
            'chargeback_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        abort_unless($this->userCanAccessBrand($request, (int) $validated['brand_id']), 403);

        $agent = User::query()
            ->whereKey($validated['agent_id'])
            ->where('department', 'Sales')
            ->where('is_commission_eligible', true)
            ->firstOrFail();

        abort_unless($this->userCanAccessBrand($request, (int) $agent->brand_id), 403);

        if ((int) $agent->brand_id !== (int) $validated['brand_id']) {
            return back()
                ->withErrors(['agent_id' => 'The selected agent must belong to the selected brand.'])
                ->withInput();
        }

        $amount = round((float) $validated['amount'], 2);

        SalesActivity::create([
            'brand_id' => $validated['brand_id'],
            'agent_id' => $agent->id,
            'activity_type' => 'chargeback',
            'endorsement_code' => $validated['endorsement_code'] ?: 'CHB-'.now()->format('YmdHis'),
            'author_name' => $validated['author_name'],
            'book_title' => $validated['book_title'] ?? null,
            'service_name' => $validated['service_name'] ?? 'Chargeback',
            'amount' => -$amount,
            'agent_credit_amount' => -$amount,
            'frankie_credit_amount' => 0,
            'payment_method' => $validated['payment_method'] ?? null,
            'payment_status' => 'Payment Success',
            'sold_date' => $validated['chargeback_date'],
            'original_sold_date' => $validated['original_sold_date'] ?? null,
            'chargeback_reason' => $validated['chargeback_reason'] ?? null,
        ]);

        return redirect()
            ->route('reports.sales-activity.index')
            ->with('success', 'Chargeback recorded successfully. It will deduct from the agent commission for the chargeback month.');
    }

    private function canManageChargebacks(Request $request): bool
    {
        return $request->user()?->role?->name === 'Admin'
            || (bool) $request->user()?->hasPermission('manage_payment_records');
    }

    private function userCanAccessBrand(Request $request, ?int $brandId): bool
    {
        return BrandScope::canAccessAllBrands($request->user())
            || (int) $brandId === (int) BrandScope::userBrandId($request->user());
    }
}
