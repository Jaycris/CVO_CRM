<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\ProductionProject;
use App\Models\SalesEndorsement;
use App\Models\SalesPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrashController extends Controller
{
    public function index(Request $request): View
    {
        $this->ensureAdmin($request);

        $type = $this->validType($request->query('type', 'users'));
        $search = trim((string) $request->query('search', ''));

        return view('admin.trash.index', [
            'type' => $type,
            'search' => $search,
            'records' => $this->records($type, $search),
            'counts' => [
                'users' => User::onlyTrashed()->count(),
                'leads' => Lead::onlyTrashed()->count(),
                'endorsements' => SalesEndorsement::onlyTrashed()->count(),
                'payments' => SalesPayment::onlyTrashed()->count(),
                'projects' => ProductionProject::onlyTrashed()->count(),
            ],
        ]);
    }

    public function restore(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'type' => ['required', 'in:users,leads,endorsements,payments,projects'],
            'record_ids' => ['required', 'array', 'min:1'],
            'record_ids.*' => ['integer'],
        ]);

        $this->modelClass($validated['type'])::onlyTrashed()
            ->whereIn('id', $validated['record_ids'])
            ->get()
            ->each
            ->restore();

        return back()->with('success', 'Selected record(s) restored successfully.');
    }

    public function forceDestroy(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'type' => ['required', 'in:users,leads,endorsements,payments,projects'],
            'record_ids' => ['required', 'array', 'min:1'],
            'record_ids.*' => ['integer'],
        ]);

        $this->modelClass($validated['type'])::onlyTrashed()
            ->whereIn('id', $validated['record_ids'])
            ->get()
            ->each(function (Model $record) {
                $record->forceDelete();
            });

        return back()->with('success', 'Selected record(s) permanently deleted.');
    }

    private function records(string $type, string $search)
    {
        return match ($type) {
            'users' => User::onlyTrashed()
                ->with('role')
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($query) use ($search) {
                        $query->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('department', 'like', "%{$search}%");
                    });
                })
                ->latest('deleted_at')
                ->paginate(10)
                ->withQueryString(),
            'leads' => Lead::onlyTrashed()
                ->with(['createdBy', 'assignedUser'])
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($query) use ($search) {
                        $query->where('publisher', 'like', "%{$search}%")
                            ->orWhere('book_title', 'like', "%{$search}%")
                            ->orWhere('author_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                })
                ->latest('deleted_at')
                ->paginate(10)
                ->withQueryString(),
            'endorsements' => SalesEndorsement::onlyTrashed()
                ->with('agent')
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($query) use ($search) {
                        $query->where('author_name', 'like', "%{$search}%")
                            ->orWhere('book_title', 'like', "%{$search}%")
                            ->orWhere('services', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhereHas('agent', function ($query) use ($search) {
                                $query->where('first_name', 'like', "%{$search}%")
                                    ->orWhere('last_name', 'like', "%{$search}%");
                            });
                    });
                })
                ->latest('deleted_at')
                ->paginate(10)
                ->withQueryString(),
            'payments' => SalesPayment::onlyTrashed()
                ->with(['endorsement' => fn ($query) => $query->withTrashed()->with('agent')])
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($query) use ($search) {
                        $query->where('payment_method', 'like', "%{$search}%")
                            ->orWhere('status', 'like', "%{$search}%")
                            ->orWhereHas('endorsement', function ($query) use ($search) {
                                $query->withTrashed()
                                    ->where('author_name', 'like', "%{$search}%")
                                    ->orWhere('book_title', 'like', "%{$search}%");
                            });
                    });
                })
                ->latest('deleted_at')
                ->paginate(10)
                ->withQueryString(),
            'projects' => ProductionProject::onlyTrashed()
                ->with([
                    'assignedUser',
                    'fulfillmentOfficer',
                    'endorsement' => fn ($query) => $query->withTrashed()->with('agent'),
                ])
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($query) use ($search) {
                        $query->where('tracker_type', 'like', "%{$search}%")
                            ->orWhere('status', 'like', "%{$search}%")
                            ->orWhereHas('endorsement', function ($query) use ($search) {
                                $query->withTrashed()
                                    ->where('author_name', 'like', "%{$search}%")
                                    ->orWhere('book_title', 'like', "%{$search}%")
                                    ->orWhere('services', 'like', "%{$search}%");
                            })
                            ->orWhereHas('assignedUser', function ($query) use ($search) {
                                $query->where('first_name', 'like', "%{$search}%")
                                    ->orWhere('last_name', 'like', "%{$search}%");
                            });
                    });
                })
                ->latest('deleted_at')
                ->paginate(10)
                ->withQueryString(),
        };
    }

    private function modelClass(string $type): string
    {
        return match ($type) {
            'users' => User::class,
            'leads' => Lead::class,
            'endorsements' => SalesEndorsement::class,
            'payments' => SalesPayment::class,
            'projects' => ProductionProject::class,
        };
    }

    private function validType(string $type): string
    {
        return in_array($type, ['users', 'leads', 'endorsements', 'payments', 'projects'], true) ? $type : 'users';
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()?->role?->name === 'Admin', 403);
    }
}
