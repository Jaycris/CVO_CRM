<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\DashboardBanner;
use App\Support\BrandScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardBannerController extends Controller
{
    public function index(Request $request): View
    {
        $this->ensureCanManageBanners($request);

        $search = trim((string) $request->query('search', ''));
        $brandFilter = $request->query('brand_id');

        $banners = DashboardBanner::query()
            ->with(['brand', 'creator'])
            ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
            ->when($brandFilter && BrandScope::canAccessAllBrands($request->user()), fn ($query) => $query->where('brand_id', $brandFilter))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('message', 'like', "%{$search}%")
                        ->orWhere('type', 'like', "%{$search}%")
                        ->orWhereHas('brand', fn ($query) => $query->where('imprint_name', 'like', "%{$search}%"));
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
        $types = $this->types();

        return view('admin.dashboard-banners.index', compact(
            'banners',
            'brands',
            'defaultBrandId',
            'types',
            'search',
            'brandFilter'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManageBanners($request);

        $validated = $this->validatedBanner($request);
        abort_unless($this->userCanAccessBrand($request, (int) $validated['brand_id']), 403);

        $imagePath = $request->file('image')?->store('dashboard-banners', 'public');

        DashboardBanner::create([
            ...$validated,
            'created_by' => $request->user()->id,
            'is_active' => $request->boolean('is_active'),
            'image_path' => $imagePath,
        ]);

        return redirect()
            ->route('admin.dashboard-banners.index')
            ->with('success', 'Dashboard banner created successfully.');
    }

    public function update(Request $request, DashboardBanner $dashboardBanner): RedirectResponse
    {
        $this->ensureCanManageBanners($request);
        abort_unless($this->userCanAccessBrand($request, $dashboardBanner->brand_id), 403);

        $validated = $this->validatedBanner($request);
        abort_unless($this->userCanAccessBrand($request, (int) $validated['brand_id']), 403);

        $imagePath = $dashboardBanner->image_path;

        if ($request->hasFile('image')) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }

            $imagePath = $request->file('image')->store('dashboard-banners', 'public');
        }

        $dashboardBanner->update([
            ...$validated,
            'is_active' => $request->boolean('is_active'),
            'image_path' => $imagePath,
        ]);

        return redirect()
            ->route('admin.dashboard-banners.index', $request->only('search', 'brand_id'))
            ->with('success', 'Dashboard banner updated successfully.');
    }

    public function destroy(Request $request, DashboardBanner $dashboardBanner): RedirectResponse
    {
        $this->ensureCanManageBanners($request);
        abort_unless($this->userCanAccessBrand($request, $dashboardBanner->brand_id), 403);

        $dashboardBanner->delete();

        return back()->with('success', 'Dashboard banner removed successfully.');
    }

    private function validatedBanner(Request $request): array
    {
        $validated = $request->validate([
            'brand_id' => ['required', 'exists:brands,id'],
            'type' => ['required', Rule::in(array_keys($this->types()))],
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:1000'],
            'button_text' => ['nullable', 'string', 'max:60'],
            'button_url' => ['nullable', 'string', 'max:2048'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'image' => ['nullable', 'mimes:jpg,jpeg,png,webp,gif', 'max:8192'],
        ]);

        unset($validated['image']);

        return $validated;
    }

    private function types(): array
    {
        return [
            'congratulations' => 'Congratulations',
            'event' => 'Upcoming Event',
            'announcement' => 'Announcement',
        ];
    }

    private function ensureCanManageBanners(Request $request): void
    {
        abort_unless(
            $request->user()?->role?->name === 'Admin'
            || $request->user()?->hasPermission('manage_dashboard_banners'),
            403
        );
    }

    private function userCanAccessBrand(Request $request, ?int $brandId): bool
    {
        return BrandScope::canAccessAllBrands($request->user())
            || (int) $request->user()?->brand_id === (int) $brandId;
    }
}
