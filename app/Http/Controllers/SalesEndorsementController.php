<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\SalesEndorsement;
use App\Models\Service;
use App\Models\User;
use App\Notifications\SalesEndorsementSubmittedNotification;
use App\Support\BrandScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesEndorsementController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($this->canViewEndorsements($request), 403);

        $search = trim((string) $request->query('search', ''));

        $endorsements = SalesEndorsement::with(['agent', 'brand'])
            ->tap(fn ($query) => $this->applyEndorsementBrandScope($query, $request))
            ->when(! $this->canViewAllEndorsements($request), fn ($query) => $query->where('agent_id', $request->user()->id))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('endorsement_code', 'like', "%{$search}%")
                        ->orWhere('author_name', 'like', "%{$search}%")
                        ->orWhere('book_title', 'like', "%{$search}%")
                        ->orWhere('services', 'like', "%{$search}%")
                        ->orWhere('payment', 'like', "%{$search}%")
                        ->orWhere('contact_number', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('isbn', 'like', "%{$search}%")
                        ->orWhereHas('agent', function ($query) use ($search) {
                            $query->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('sales-endorsements.index', [
            'endorsements' => $endorsements,
            'canSubmitEndorsement' => $this->userHasPermission($request, 'submit_sales_endorsement'),
            'canDeleteEndorsements' => $this->userHasPermission($request, 'delete_sales_endorsements'),
            'isAdmin' => $request->user()?->role?->name === 'Admin',
            'search' => $search,
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->userHasPermission($request, 'submit_sales_endorsement'), 403);

        return view('sales-endorsements.create', [
            'paymentOptions' => ['First Payment', 'Recurring', 'Final Payment', 'Full Payment'],
            'serviceOptions' => $this->serviceOptions($request),
            'leadOptions' => $this->leadOptions($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->userHasPermission($request, 'submit_sales_endorsement'), 403);

        $validated = $request->validate([
            'has_frankie' => ['nullable', 'boolean'],
            'frankie_agent_name' => ['required_if:has_frankie,1', 'nullable', 'string', 'max:255'],
            'lead_id' => ['nullable', 'exists:leads,id'],
            'author_name' => ['required', 'string', 'max:255'],
            'contact_number' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'street_name' => ['required', 'string', 'max:255'],
            'city_state' => ['required', 'string', 'max:255'],
            'zip_code' => ['required', 'string', 'max:50'],
            'book_title' => ['required', 'string', 'max:255'],
            'isbn' => ['required', 'string', 'max:255'],
            'services' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'payment' => ['required', 'in:First Payment,Recurring,Final Payment,Full Payment'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $lead = isset($validated['lead_id'])
            ? Lead::find($validated['lead_id'])
            : null;

        abort_if($lead && ! $this->userCanAccessBrand($request, $lead->brand_id), 403);

        $brandId = $lead?->brand_id ?? BrandScope::userBrandId($request->user());
        $serviceId = Service::query()
            ->where('brand_id', $brandId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($validated['services']))])
            ->value('id');

        $endorsement = SalesEndorsement::create([
            ...$validated,
            'brand_id' => $brandId,
            'service_id' => $serviceId,
            'agent_id' => $request->user()->id,
            'has_frankie' => $request->boolean('has_frankie'),
        ]);

        $this->notifyFinanceUsers($endorsement->loadMissing('agent'));

        return redirect()
            ->route('sales.endorsements.index')
            ->with('success', 'Sales endorsement submitted successfully.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        abort_unless($this->userHasPermission($request, 'delete_sales_endorsements'), 403);

        $validated = $request->validate([
            'endorsement_ids' => ['required', 'array', 'min:1'],
            'endorsement_ids.*' => ['integer', 'exists:sales_endorsements,id'],
        ]);

        SalesEndorsement::whereIn('id', $validated['endorsement_ids'])
            ->tap(fn ($query) => $this->applyEndorsementBrandScope($query, $request))
            ->get()
            ->each
            ->delete();

        return back()->with('success', 'Selected sales endorsement record(s) deleted successfully.');
    }

    private function userHasPermission(Request $request, string $permission): bool
    {
        return $request->user()?->role?->name === 'Admin'
            || (bool) $request->user()?->hasPermission($permission);
    }

    private function canViewEndorsements(Request $request): bool
    {
        return $this->userHasPermission($request, 'view_sales_endorsement_form')
            || $this->userHasPermission($request, 'view_own_sales_endorsements')
            || $this->userHasPermission($request, 'view_all_sales_endorsements');
    }

    private function canViewAllEndorsements(Request $request): bool
    {
        return $request->user()?->role?->name === 'Admin'
            || $request->user()?->role?->name === 'Finance Officer'
            || $request->user()?->hasPermission('view_all_sales_endorsements')
            || $request->user()?->hasPermission('view_payment_records')
            || $request->user()?->hasPermission('view_finance_clients')
            || $request->user()?->hasPermission('view_contract_records');
    }

    private function serviceOptions(Request $request): array
    {
        $configuredServices = Service::query()
            ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
            ->orderBy('name')
            ->pluck('name')
            ->all();

        $fallbackServices = [
            'Publishing',
            'Branding',
            'Website Design',
            'Book Trailer',
            'Press Release',
            'Marketing Campaign',
        ];

        return collect($configuredServices)
            ->merge($fallbackServices)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function leadOptions(Request $request): array
    {
        return Lead::query()
            ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
            ->when($request->user()?->role?->name !== 'Admin', function ($query) use ($request) {
                $query->where(function ($query) use ($request) {
                    $query->where('assigned_to', $request->user()->id)
                        ->orWhere('created_by', $request->user()->id);
                });
            })
            ->orderBy('author_name')
            ->get()
            ->map(fn (Lead $lead) => [
                'id' => $lead->id,
                'authorName' => $lead->author_name,
                'bookTitle' => $lead->book_title,
                'contactNumber' => collect($lead->phone_numbers ?? [])->first() ?? '',
                'email' => $lead->email ?? '',
            ])
            ->values()
            ->all();
    }

    private function applyEndorsementBrandScope($query, Request $request): void
    {
        BrandScope::apply($query, $request->user());
    }

    private function userCanAccessBrand(Request $request, ?int $brandId): bool
    {
        return BrandScope::canAccessAllBrands($request->user())
            || (int) $request->user()?->brand_id === (int) $brandId;
    }

    private function notifyFinanceUsers(SalesEndorsement $endorsement): void
    {
        User::with(['role.permissionRecords', 'permissionOverrides', 'brand'])
            ->whereNull('suspended_at')
            ->get()
            ->filter(fn (User $user) => $this->canReceiveFinanceEndorsementNotification($user, $endorsement))
            ->each(function (User $user) use ($endorsement) {
                $existingNotification = $user->unreadNotifications()
                    ->where('type', SalesEndorsementSubmittedNotification::class)
                    ->latest()
                    ->first();

                if (! $existingNotification) {
                    $user->notify(new SalesEndorsementSubmittedNotification($endorsement));

                    return;
                }

                $nextCount = ((int) ($existingNotification->data['count'] ?? 1)) + 1;

                $existingNotification->forceFill([
                    'data' => (new SalesEndorsementSubmittedNotification($endorsement, $nextCount))->toArray($user),
                    'updated_at' => now(),
                ])->save();
            });
    }

    private function canReceiveFinanceEndorsementNotification(User $user, SalesEndorsement $endorsement): bool
    {
        if (! BrandScope::canAccessAllBrands($user) && (int) $user->brand_id !== (int) $endorsement->brand_id) {
            return false;
        }

        return $user->role?->name === 'Admin'
            || $user->role?->name === 'Finance Officer'
            || (
                $user->department === 'Finance'
                && (
                    $user->hasPermission('view_all_sales_endorsements')
                    || $user->hasPermission('view_sales_endorsement_form')
                    || $user->hasPermission('view_payment_records')
                    || $user->hasPermission('view_finance_clients')
                    || $user->hasPermission('view_contract_records')
                )
            );
    }
}
