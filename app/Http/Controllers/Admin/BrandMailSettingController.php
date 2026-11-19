<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\BrandMailSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BrandMailSettingController extends Controller
{
    public function edit(Request $request, Brand $brand)
    {
        abort_unless($request->user()?->role?->name === 'Admin', 403);
        return view('admin.brands.email', ['brand' => $brand,
            'setting' => BrandMailSetting::where('brand_id', $brand->id)->first()]);
    }

    public function update(Request $request, Brand $brand)
    {
        abort_unless($request->user()?->role?->name === 'Admin', 403);
        $enabled = $request->boolean('is_enabled');
        $validated = $request->validate([
            'is_enabled' => ['required', 'boolean'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'email_address' => [Rule::requiredIf($enabled), 'nullable', 'email', 'max:255'],
            'reply_to_address' => ['nullable', 'email', 'max:255'],
            'smtp_host' => [Rule::requiredIf($enabled), 'nullable', 'string', 'max:255'],
            'smtp_port' => [Rule::requiredIf($enabled), 'nullable', 'integer', 'between:1,65535'],
            'smtp_encryption' => [Rule::requiredIf($enabled), 'nullable', Rule::in(['ssl', 'tls'])],
            'username' => [Rule::requiredIf($enabled), 'nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:4096'],
        ]);
        DB::transaction(function () use ($brand, $validated, $enabled) {
            Brand::whereKey($brand->id)->lockForUpdate()->firstOrFail();
            $setting = BrandMailSetting::firstOrNew(['brand_id' => $brand->id]);
            if ($enabled && empty($validated['password']) && ! $setting->encrypted_password) {
                throw ValidationException::withMessages(['password' => 'Enter the SMTP password before enabling this brand sender.']);
            }
            $setting->fill(collect($validated)->except('password')->all());
            $setting->display_name = trim((string) ($validated['display_name'] ?? '')) ?: $brand->imprint_name;
            $setting->email_address = isset($validated['email_address']) ? mb_strtolower(trim($validated['email_address'])) : null;
            $setting->setPlainPassword($validated['password'] ?? null);
            $setting->save();
        });
        return back()->with('success', 'Brand sender settings saved.');
    }
}
