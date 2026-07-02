<?php

namespace App\Http\Controllers;

use App\Models\SalesActivity;
use App\Support\BrandScope;
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

        $activities = SalesActivity::with(['brand', 'agent', 'leadMiner', 'verifier', 'service'])
            ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('endorsement_code', 'like', "%{$search}%")
                        ->orWhere('author_name', 'like', "%{$search}%")
                        ->orWhere('book_title', 'like', "%{$search}%")
                        ->orWhere('service_name', 'like', "%{$search}%")
                        ->orWhere('payment_status', 'like', "%{$search}%")
                        ->orWhereHas('brand', fn ($query) => $query->where('imprint_name', 'like', "%{$search}%"))
                        ->orWhereHas('agent', function ($query) use ($search) {
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
            ->paginate(10)
            ->withQueryString();

        return view('sales-activities.index', [
            'activities' => $activities,
            'search' => $search,
        ]);
    }
}
