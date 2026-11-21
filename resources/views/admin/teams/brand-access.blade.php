<div class="rounded-xl border border-slate-200 p-4 dark:border-zinc-700">
    <input type="hidden" name="multiple_brands_enabled" value="0">
    <label class="flex items-center gap-3 text-sm font-semibold">
        <input type="checkbox" name="multiple_brands_enabled" value="1" x-model="multipleBrandsEnabled" x-on:change="cleanSelections()" class="rounded text-emerald-700">
        Enable Multiple Brands
    </label>
    <div x-show="multipleBrandsEnabled" x-cloak class="mt-3 space-y-3">
        <p class="text-sm" x-text="allowedBrandIds.length + ' eligible brand(s) selected'"></p>
        <div class="flex flex-wrap gap-3">
            @foreach ($brands->where('is_sales_brand', true) as $eligibleBrand)
                <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 dark:border-zinc-700">
                    <input type="checkbox" name="allowed_brand_ids[]" value="{{ $eligibleBrand->id }}" x-model="allowedBrandIds" :disabled="!multipleBrandsEnabled" x-on:change="cleanSelections()" class="rounded text-emerald-700">
                    {{ $eligibleBrand->imprint_name }}
                </label>
            @endforeach
        </div>
        <p class="text-xs text-slate-500">Select at least two brands, including the primary sales brand. Team membership does not change a user’s individual brand access.</p>
        <p x-show="department === 'Sales'" class="text-xs text-slate-500">Monthly team targets are set separately for each brand in Sales Dashboard MTD.</p>
    </div>
    <x-input-error :messages="$errors->get('allowed_brand_ids')" class="mt-2" />
    <x-input-error :messages="$errors->get('allowed_brand_ids.*')" class="mt-2" />
</div>
