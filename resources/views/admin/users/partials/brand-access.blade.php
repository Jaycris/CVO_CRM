<div class="md:col-span-2 rounded-xl border border-slate-200 p-4 dark:border-zinc-700"
     x-data="{
        enabled: @js((bool) old('multiple_brands_enabled', isset($user) ? $user->multiple_brands_enabled : false)),
        selected: @js(array_map('strval', old('allowed_brand_ids', isset($user) ? $user->accessibleBrands->pluck('id')->all() : [])))
     }">
    <input type="hidden" name="multiple_brands_enabled" value="0">
    <label class="flex items-center gap-3 font-semibold text-slate-800 dark:text-zinc-100">
        <input type="checkbox" name="multiple_brands_enabled" value="1" x-model="enabled" class="rounded border-slate-300 text-emerald-700">
        Enable Multiple Brands
    </label>
    <p class="mt-2 text-sm text-slate-500">Disabled: use the primary Brand / Account above. Enabled: select at least two eligible sales brands, including the primary sales brand.</p>
    <div x-show="enabled" x-cloak class="mt-4 space-y-3">
        <p class="text-sm font-semibold" x-text="selected.length + ' eligible brand(s) selected'"></p>
        <div class="flex flex-wrap gap-4">
            @foreach ($brands->where('is_sales_brand', true) as $eligibleBrand)
                <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-4 py-3 dark:border-zinc-700">
                    <input type="checkbox" name="allowed_brand_ids[]" value="{{ $eligibleBrand->id }}" x-model="selected" :disabled="!enabled" class="rounded border-slate-300 text-emerald-700">
                    <span>{{ $eligibleBrand->imprint_name }}</span>
                </label>
            @endforeach
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($brands->where('is_sales_brand', true) as $eligibleBrand)
                <div x-show="selected.includes('{{ $eligibleBrand->id }}')">
                    <label for="brand-email-{{ $eligibleBrand->id }}" class="mb-2 block text-sm font-medium">Employee Email — {{ $eligibleBrand->imprint_name }}</label>
                    <input id="brand-email-{{ $eligibleBrand->id }}" type="email" name="brand_emails[{{ $eligibleBrand->id }}]"
                           value="{{ old('brand_emails.'.$eligibleBrand->id, isset($user) ? $user->emailForBrand($eligibleBrand->id) : '') }}"
                           :disabled="!enabled || !selected.includes('{{ $eligibleBrand->id }}')"
                           placeholder="name@brand-domain.com"
                           class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                    <x-input-error :messages="$errors->get('brand_emails.'.$eligibleBrand->id)" class="mt-2" />
                </div>
            @endforeach
        </div>
        <p class="text-sm text-slate-500">These are the employee’s contact addresses for each brand. The login email below remains the account’s sign-in address.</p>
        <p class="text-sm text-slate-500">Sales across these brands share one monthly agent target, commission tier, and threshold. Existing records keep their brand.</p>
    </div>
    <x-input-error :messages="$errors->get('allowed_brand_ids')" class="mt-2" />
    <x-input-error :messages="$errors->get('allowed_brand_ids.*')" class="mt-2" />
    <x-input-error :messages="$errors->get('multiple_brands_enabled')" class="mt-2" />
    <x-input-error :messages="$errors->get('brand_emails')" class="mt-2" />
</div>
