<?php

namespace App\Http\Controllers;

use App\Models\SalesEndorsement;
use App\Models\ProductionProject;
use App\Models\User;
use App\Notifications\ProductionProjectsEndorsedNotification;
use App\Support\BrandScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinanceContractController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($this->userHasPermission($request, 'view_contract_records'), 403);

        $status = $request->query('status', 'all');
        $search = trim((string) $request->query('search', ''));
        $endorsements = SalesEndorsement::with(['agent', 'brand', 'paymentRecord', 'productionProject'])
            ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
            ->whereHas('paymentRecord', fn ($query) => $query->where('status', 'Payment Success'))
            ->when($status === 'sent', fn ($query) => $query->where('contract_status', 'sent'))
            ->when($status === 'signed', fn ($query) => $query->where('contract_status', 'signed'))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('author_name', 'like', "%{$search}%")
                        ->orWhere('book_title', 'like', "%{$search}%")
                        ->orWhere('services', 'like', "%{$search}%")
                        ->orWhere('amount', 'like', "%{$search}%")
                        ->orWhere('contract_status', 'like', "%{$search}%")
                        ->orWhereHas('agent', function ($query) use ($search) {
                            $query->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('finance.contracts', [
            'endorsements' => $endorsements,
            'status' => $status,
            'search' => $search,
            'canManageContracts' => $this->userHasPermission($request, 'manage_contract_records'),
            'canDeleteContracts' => $this->userHasPermission($request, 'delete_payment_records'),
            'canEndorseProduction' => $this->userHasPermission($request, 'endorse_projects_to_production'),
        ]);
    }

    public function update(Request $request, SalesEndorsement $endorsement): RedirectResponse
    {
        abort_unless($this->userHasPermission($request, 'manage_contract_records'), 403);

        $validated = $request->validate([
            'contract_status' => ['required', 'in:sent,signed'],
        ]);

        $updates = ['contract_status' => $validated['contract_status']];

        if ($validated['contract_status'] === 'sent' && ! $endorsement->contract_sent_at) {
            $updates['contract_sent_at'] = now();
        }

        if ($validated['contract_status'] === 'signed') {
            $updates['contract_sent_at'] = $endorsement->contract_sent_at ?? now();
            $updates['contract_signed_at'] = now();
        }

        abort_unless($this->userCanAccessBrand($request, $endorsement->brand_id), 403);

        $endorsement->update($updates);

        return redirect()
            ->route('finance.contracts.index', ['status' => $request->query('status', 'all')])
            ->with('success', 'Contract status updated successfully.');
    }

    public function bulkUpdate(Request $request): RedirectResponse
    {
        abort_unless($this->userHasPermission($request, 'manage_contract_records'), 403);

        $validated = $request->validate([
            'endorsement_ids' => ['required', 'array', 'min:1'],
            'endorsement_ids.*' => ['integer', 'exists:sales_endorsements,id'],
            'contract_status' => ['required', 'in:sent,signed'],
            'status' => ['nullable', 'in:all,sent,signed'],
        ]);

        SalesEndorsement::whereIn('id', $validated['endorsement_ids'])
            ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
            ->get()
            ->each(function (SalesEndorsement $endorsement) use ($validated) {
                $updates = ['contract_status' => $validated['contract_status']];

                if ($validated['contract_status'] === 'sent' && ! $endorsement->contract_sent_at) {
                    $updates['contract_sent_at'] = now();
                }

                if ($validated['contract_status'] === 'signed') {
                    $updates['contract_sent_at'] = $endorsement->contract_sent_at ?? now();
                    $updates['contract_signed_at'] = now();
                }

                $endorsement->update($updates);
            });

        return redirect()
            ->route('finance.contracts.index', ['status' => $validated['status'] ?? 'all'])
            ->with('success', 'Selected contract status updated successfully.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        abort_unless($this->userHasPermission($request, 'delete_payment_records'), 403);

        $validated = $request->validate([
            'endorsement_ids' => ['required', 'array', 'min:1'],
            'endorsement_ids.*' => ['integer', 'exists:sales_endorsements,id'],
            'status' => ['nullable', 'in:all,sent,signed'],
        ]);

        SalesEndorsement::whereIn('id', $validated['endorsement_ids'])
            ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
            ->update([
            'contract_status' => null,
            'contract_sent_at' => null,
            'contract_signed_at' => null,
        ]);

        return redirect()
            ->route('finance.contracts.index', ['status' => $validated['status'] ?? 'all'])
            ->with('success', 'Selected contract record(s) deleted successfully.');
    }

    public function endorseToProduction(Request $request): RedirectResponse
    {
        abort_unless($this->userHasPermission($request, 'endorse_projects_to_production'), 403);

        $validated = $request->validate([
            'endorsement_ids' => ['required', 'array', 'min:1'],
            'endorsement_ids.*' => ['integer', 'exists:sales_endorsements,id'],
            'tracker_type' => ['required', 'in:publishing,marketing,events'],
            'endorsement_notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'in:all,sent,signed'],
        ]);

        $endorsedCount = 0;
        $notifications = [];

        SalesEndorsement::with('paymentRecord')
            ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
            ->whereIn('id', $validated['endorsement_ids'])
            ->get()
            ->each(function (SalesEndorsement $endorsement) use ($request, $validated, &$endorsedCount, &$notifications) {
                $fulfillmentOfficer = $this->leastLoadedFulfillmentOfficer($endorsement->brand_id);
                $project = ProductionProject::withTrashed()
                    ->firstOrNew(['sales_endorsement_id' => $endorsement->id]);

                if ($project->trashed()) {
                    $project->restore();
                }

                $project->fill([
                    'brand_id' => $endorsement->brand_id ?? BrandScope::userBrandId($request->user()),
                    'tracker_type' => $validated['tracker_type'],
                    'fulfillment_officer_id' => $fulfillmentOfficer?->id,
                    'endorsed_by' => $request->user()->id,
                    'endorsed_at' => now(),
                    'endorsement_notes' => $validated['endorsement_notes'] ?? null,
                    'status' => 'pending',
                ])->save();

                $endorsedCount++;

                if ($fulfillmentOfficer && $this->userCanReceiveProductionNotification($fulfillmentOfficer)) {
                    $notifications[$fulfillmentOfficer->id]['user'] = $fulfillmentOfficer;
                    $notifications[$fulfillmentOfficer->id]['tracker'] = $validated['tracker_type'];
                    $notifications[$fulfillmentOfficer->id]['count'] = ($notifications[$fulfillmentOfficer->id]['count'] ?? 0) + 1;
                }
            });

        collect($notifications)->each(function (array $group) {
            $group['user']->notify(new ProductionProjectsEndorsedNotification(
                $group['count'],
                $group['tracker']
            ));
        });

        return redirect()
            ->route('finance.contracts.index', ['status' => $validated['status'] ?? 'all'])
            ->with(
                $endorsedCount > 0 ? 'success' : 'error',
                $endorsedCount > 0
                    ? "{$endorsedCount} project(s) endorsed to Production successfully."
                    : 'No selected contract records were endorsed to Production.'
            );
    }

    private function userHasPermission(Request $request, string $permission): bool
    {
        return $request->user()?->role?->name === 'Admin'
            || (bool) $request->user()?->hasPermission($permission);
    }

    private function leastLoadedFulfillmentOfficer(?int $brandId = null): ?User
    {
        return User::query()
            ->where('department', 'Production')
            ->whereNull('suspended_at')
            ->when($brandId, function ($query) use ($brandId) {
                $query->where(function ($query) use ($brandId) {
                    $query->where('brand_id', $brandId)
                        ->orWhere('brand_id', BrandScope::parentBrandId());
                });
            })
            ->whereHas('role', fn ($query) => $query->where('name', 'Fulfillment Officer'))
            ->withCount([
                'fulfillmentProjects as active_production_projects_count' => fn ($query) => $query
                    ->whereIn('status', ['pending', 'in_progress', 'hold_off']),
            ])
            ->orderBy('active_production_projects_count')
            ->orderBy('first_name')
            ->first();
    }

    private function userCanReceiveProductionNotification(User $user): bool
    {
        return $user->role?->name === 'Admin'
            || $user->hasPermission('view_all_fulfillment_trackers')
            || $user->hasPermission('view_publishing_tracker')
            || $user->hasPermission('view_marketing_tracker')
            || $user->hasPermission('view_events_tracker');
    }

    private function userCanAccessBrand(Request $request, ?int $brandId): bool
    {
        return BrandScope::canAccessAllBrands($request->user())
            || (int) $request->user()?->brand_id === (int) $brandId;
    }

}
