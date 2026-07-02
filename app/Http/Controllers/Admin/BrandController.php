<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BrandController extends Controller
{
    public function index(): View
    {
        $this->ensureAdmin(request());

        $brands = Brand::query()
            ->orderBy('imprint_name')
            ->paginate(10)
            ->withQueryString();

        return view('admin.brands.index', compact('brands'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'imprint_name' => ['required', 'string', 'max:255', 'unique:brands,imprint_name'],
            'crm_display_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'address' => ['nullable', 'string', 'max:5000'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'site_logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,ico', 'max:1024'],
        ]);

        Brand::create([
            'imprint_name' => trim($validated['imprint_name']),
            'crm_display_name' => $this->crmDisplayName($validated['crm_display_name'] ?? null, $validated['imprint_name']),
            'description' => $validated['description'] ? trim($validated['description']) : null,
            'address' => $validated['address'] ? trim($validated['address']) : null,
            'logo_path' => $request->file('logo')?->store('brand-logos', 'public'),
            'primary_color' => $validated['primary_color'],
            'accent_color' => $validated['accent_color'],
            'site_logo_path' => $request->file('site_logo')?->store('brand-site-logos', 'public'),
        ]);

        return redirect()
            ->route('admin.brands.index')
            ->with('success', 'Brand created successfully.');
    }

    public function update(Request $request, Brand $brand): RedirectResponse
    {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'imprint_name' => ['required', 'string', 'max:255', Rule::unique('brands', 'imprint_name')->ignore($brand)],
            'crm_display_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'address' => ['nullable', 'string', 'max:5000'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'site_logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,ico', 'max:1024'],
        ]);

        $logoPath = $brand->logo_path;
        $siteLogoPath = $brand->site_logo_path;

        if ($request->hasFile('logo')) {
            if ($logoPath) {
                Storage::disk('public')->delete($logoPath);
            }

            $logoPath = $request->file('logo')->store('brand-logos', 'public');
        }

        if ($request->hasFile('site_logo')) {
            if ($siteLogoPath) {
                Storage::disk('public')->delete($siteLogoPath);
            }

            $siteLogoPath = $request->file('site_logo')->store('brand-site-logos', 'public');
        }

        $brand->update([
            'imprint_name' => trim($validated['imprint_name']),
            'crm_display_name' => $this->crmDisplayName($validated['crm_display_name'] ?? null, $validated['imprint_name']),
            'description' => $validated['description'] ? trim($validated['description']) : null,
            'address' => $validated['address'] ? trim($validated['address']) : null,
            'logo_path' => $logoPath,
            'primary_color' => $validated['primary_color'],
            'accent_color' => $validated['accent_color'],
            'site_logo_path' => $siteLogoPath,
        ]);

        return redirect()
            ->route('admin.brands.index')
            ->with('success', 'Brand updated successfully.');
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()?->role?->name === 'Admin', 403);
    }

    private function crmDisplayName(?string $value, string $imprintName): string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : trim($imprintName) . ' CRM';
    }
}
