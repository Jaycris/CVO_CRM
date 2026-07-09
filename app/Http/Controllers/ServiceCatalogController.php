<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Service;
use App\Support\BrandScope;
use Illuminate\Http\Request;

class ServiceCatalogController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(
            $request->user()?->role?->name === 'Admin'
                || $request->user()?->hasPermission('view_services_catalog')
                || $request->user()?->hasPermission('manage_services'),
            403
        );

        $search = trim((string) $request->query('search', ''));
        $category = $request->query('category');
        $brandFilter = $request->query('brand_id');
        $canFilterBrands = BrandScope::canAccessAllBrands($request->user());

        $servicesQuery = Service::query()
            ->with(['brand', 'inclusions' => fn ($query) => $query->orderBy('sort_order')->orderBy('name')])
            ->latest();

        BrandScope::apply($servicesQuery, $request->user());

        if ($canFilterBrands && $brandFilter) {
            $servicesQuery->where('brand_id', $brandFilter);
        }

        if ($category) {
            $servicesQuery->where('category', $category);
        }

        if ($search !== '') {
            $servicesQuery->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('brand', fn ($brandQuery) => $brandQuery->where('imprint_name', 'like', "%{$search}%"))
                    ->orWhereHas('inclusions', fn ($inclusionQuery) => $inclusionQuery->where('name', 'like', "%{$search}%"));
            });
        }

        $services = $servicesQuery
            ->paginate(10)
            ->withQueryString();

        $brandsQuery = Brand::query()->orderBy('imprint_name');
        BrandScope::apply($brandsQuery, $request->user(), 'id');

        return view('services.index', [
            'services' => $services,
            'brands' => $brandsQuery->get(),
            'categories' => ['Publishing', 'Marketing', 'Events'],
            'search' => $search,
            'category' => $category,
            'brandFilter' => $brandFilter,
            'canFilterBrands' => $canFilterBrands,
        ]);
    }
}
