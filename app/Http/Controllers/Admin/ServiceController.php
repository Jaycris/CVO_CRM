<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Service;
use App\Models\ServiceInclusion;
use App\Support\BrandScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
            ->paginate(\App\Models\AppSetting::recordsPerPage())
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

        $serviceData = [
            'brand_id' => $validated['brand_id'],
            'name' => trim($validated['name']),
            'category' => $validated['category'],
            'price' => $validated['price'] ?? null,
            'description' => $this->nullableTrim($validated['description'] ?? null),
        ];

        if ($request->hasFile('pdf_file')) {
            $serviceData['pdf_path'] = $request->file('pdf_file')->store('service-pdfs', 'public');
        }

        $service = Service::create($serviceData);

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

        $serviceData = [
            'brand_id' => $validated['brand_id'],
            'name' => trim($validated['name']),
            'category' => $validated['category'],
            'price' => $validated['price'] ?? null,
            'description' => $this->nullableTrim($validated['description'] ?? null),
        ];

        if ($request->hasFile('pdf_file')) {
            if ($service->pdf_path) {
                Storage::disk('public')->delete($service->pdf_path);
            }

            $serviceData['pdf_path'] = $request->file('pdf_file')->store('service-pdfs', 'public');
        }

        $service->update($serviceData);

        $this->syncInclusions($service, $validated['inclusions'] ?? []);

        return redirect()
            ->route('admin.services.index', $request->only('search', 'brand_id'))
            ->with('success', 'Service updated successfully.');
    }

    public function destroy(Request $request, Service $service): RedirectResponse
    {
        $this->ensureCanManageServices($request);
        abort_unless($this->userCanAccessBrand($request, $service->brand_id), 403);

        // Soft delete only -- the PDF is removed by the Service model's
        // forceDeleted hook so a restored service keeps a working file.
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
            'pdf_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,gif', 'max:10240'],
            'inclusions' => ['nullable', 'array'],
            'inclusions.*.id' => ['nullable', 'integer'],
            'inclusions.*.name' => ['nullable', 'string', 'max:255'],
        ]);
    }

    /**
     * Reconcile the submitted inclusions against the stored ones.
     *
     * This deliberately avoids delete-and-recreate: service_items rows are
     * referenced by production_task_items.service_item_id (nullOnDelete), so
     * recreating them on every save silently unlinked existing production
     * tasks and broke the duplicate-inclusion guard in
     * ProductionProjectController::storeTask().
     */
    private function syncInclusions(Service $service, array $inclusions): void
    {
        $submitted = collect($inclusions)
            ->map(fn ($inclusion) => [
                'id' => isset($inclusion['id']) && $inclusion['id'] !== '' ? (int) $inclusion['id'] : null,
                'name' => trim((string) ($inclusion['name'] ?? '')),
            ])
            ->filter(fn (array $inclusion) => $inclusion['name'] !== '')
            ->values();

        // Only ids already belonging to this service may be reused; anything
        // else is treated as a new row rather than trusted from the payload.
        $ownedIds = ServiceInclusion::where('service_id', $service->id)->pluck('id');
        $keptIds = [];

        $submitted->each(function (array $inclusion, int $index) use ($service, $ownedIds, &$keptIds) {
            $sortOrder = $index + 1;

            if ($inclusion['id'] !== null && $ownedIds->contains($inclusion['id'])) {
                ServiceInclusion::whereKey($inclusion['id'])
                    ->where('service_id', $service->id)
                    ->update([
                        'name' => $inclusion['name'],
                        'sort_order' => $sortOrder,
                    ]);

                $keptIds[] = $inclusion['id'];

                return;
            }

            $keptIds[] = $service->inclusions()->create([
                'name' => $inclusion['name'],
                'sort_order' => $sortOrder,
            ])->id;
        });

        // Rows the user actually removed still hard-delete, which nulls any
        // production_task_items pointing at them -- but only on removal now,
        // not on every save.
        ServiceInclusion::where('service_id', $service->id)
            ->when($keptIds !== [], fn ($query) => $query->whereNotIn('id', $keptIds))
            ->delete();
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
