<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Service;
use App\Support\BrandScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(Request $request): View
    {
        $this->ensureCanManageServices($request);

        $search = trim((string) $request->query('search', ''));
        $brandFilter = $request->query('brand_id');

        $services = Service::query()
            ->with(['brand', 'inclusions'])
            ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
            ->when($brandFilter && BrandScope::canAccessAllBrands($request->user()), fn ($query) => $query->where('brand_id', $brandFilter))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('brand', fn ($query) => $query->where('imprint_name', 'like', "%{$search}%"))
                        ->orWhereHas('inclusions', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $brands = Brand::query()
            ->tap(fn ($query) => BrandScope::apply($query, $request->user(), 'id'))
            ->orderByRaw("imprint_name = 'CreatiVision Outsourcing' desc")
            ->orderBy('imprint_name')
            ->get();

        $defaultBrandId = BrandScope::userBrandId($request->user());

        return view('admin.services.index', compact('services', 'brands', 'defaultBrandId', 'search', 'brandFilter'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManageServices($request);

        $validated = $this->validatedService($request);
        abort_unless($this->userCanAccessBrand($request, (int) $validated['brand_id']), 403);

        $service = Service::create([
            'brand_id' => $validated['brand_id'],
            'name' => trim($validated['name']),
            'category' => $validated['category'],
            'price' => $validated['price'] ?? null,
            'description' => $this->nullableTrim($validated['description'] ?? null),
        ]);

        $this->syncInclusions($service, $validated['inclusions'] ?? []);

        return redirect()
            ->route('admin.services.index')
            ->with('success', 'Service created successfully.');
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        $this->ensureCanManageServices($request);
        abort_unless($this->userCanAccessBrand($request, $service->brand_id), 403);

        $validated = $this->validatedService($request, $service);
        abort_unless($this->userCanAccessBrand($request, (int) $validated['brand_id']), 403);

        $service->update([
            'brand_id' => $validated['brand_id'],
            'name' => trim($validated['name']),
            'category' => $validated['category'],
            'price' => $validated['price'] ?? null,
            'description' => $this->nullableTrim($validated['description'] ?? null),
        ]);

        $this->syncInclusions($service, $validated['inclusions'] ?? []);

        return redirect()
            ->route('admin.services.index', $request->only('search', 'brand_id'))
            ->with('success', 'Service updated successfully.');
    }

    public function destroy(Request $request, Service $service): RedirectResponse
    {
        $this->ensureCanManageServices($request);
        abort_unless($this->userCanAccessBrand($request, $service->brand_id), 403);

        $service->delete();

        return back()->with('success', 'Service deleted successfully.');
    }

    private function validatedService(Request $request, ?Service $service = null): array
    {
        return $request->validate([
            'brand_id' => ['required', 'exists:brands,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('services', 'name')
                    ->where(fn ($query) => $query->where('brand_id', $request->input('brand_id')))
                    ->ignore($service),
            ],
            'category' => ['required', 'in:Publishing,Marketing,Events'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'description' => ['nullable', 'string', 'max:5000'],
            'inclusions' => ['nullable', 'array'],
            'inclusions.*.name' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function syncInclusions(Service $service, array $inclusions): void
    {
        $service->inclusions()->delete();

        collect($inclusions)
            ->map(fn ($inclusion) => [
                'name' => trim((string) ($inclusion['name'] ?? '')),
            ])
            ->filter(fn ($inclusion) => $inclusion['name'] !== '')
            ->values()
            ->each(function ($inclusion, int $index) use ($service) {
                $service->inclusions()->create([
                    'name' => $inclusion['name'],
                    'sort_order' => $index + 1,
                ]);
            });
    }

    private function ensureCanManageServices(Request $request): void
    {
        abort_unless(
            $request->user()?->role?->name === 'Admin'
            || $request->user()?->hasPermission('manage_services'),
            403
        );
    }

    private function userCanAccessBrand(Request $request, ?int $brandId): bool
    {
        return BrandScope::canAccessAllBrands($request->user())
            || (int) $request->user()?->brand_id === (int) $brandId;
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
