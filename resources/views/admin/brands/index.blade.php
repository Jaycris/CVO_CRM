<x-app-layout>
    <x-slot name="header">
        Brands / Accounts
    </x-slot>

    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">Brands / Accounts</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                    Manage imprints and brand accounts under CreatiVision Outsourcing.
                </p>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200">
                {{ session('success') }}
            </div>
        @endif

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-[24rem_1fr]">
            <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                <h2 class="text-lg font-bold text-slate-900 dark:text-zinc-100">Create Brand</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Add a new imprint, account, or company brand.</p>

                <form method="POST" action="{{ route('admin.brands.store') }}" enctype="multipart/form-data" class="mt-5 space-y-4">
                    @csrf

                    <div>
                        <label for="imprint_name" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                            Imprint Name <span class="text-rose-600">*</span>
                        </label>
                        <input id="imprint_name"
                               name="imprint_name"
                               type="text"
                               value="{{ old('imprint_name') }}"
                               required
                               class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        <x-input-error :messages="$errors->get('imprint_name')" class="mt-2" />
                    </div>

                    <div>
                        <label for="crm_display_name" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">CRM Display Name</label>
                        <input id="crm_display_name"
                               name="crm_display_name"
                               type="text"
                               value="{{ old('crm_display_name') }}"
                               placeholder="Example: Inkspire Media House CRM"
                               class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        <p class="mt-2 text-xs text-slate-500 dark:text-zinc-400">Shown in the browser tab after users log in. Leave blank to use the imprint name plus CRM.</p>
                        <x-input-error :messages="$errors->get('crm_display_name')" class="mt-2" />
                    </div>

                    <div>
                        <label for="description" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">Description</label>
                        <textarea id="description"
                                  name="description"
                                  rows="4"
                                  class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">{{ old('description') }}</textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>

                    <div>
                        <label for="address" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">Address</label>
                        <textarea id="address"
                                  name="address"
                                  rows="4"
                                  class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">{{ old('address') }}</textarea>
                        <x-input-error :messages="$errors->get('address')" class="mt-2" />
                    </div>

                    <div>
                        <label for="logo" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">Logo</label>
                        <input id="logo"
                               name="logo"
                               type="file"
                               accept="image/png,image/jpeg,image/webp"
                               class="w-full cursor-pointer rounded-xl border border-slate-300 px-4 py-3 text-sm shadow-sm file:mr-4 file:cursor-pointer file:rounded-lg file:border-0 file:bg-zinc-950 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-amber-100 hover:border-amber-400 hover:bg-amber-50/40 file:hover:bg-amber-700 focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:file:bg-amber-400 dark:file:text-zinc-950 dark:hover:border-amber-400 dark:hover:bg-amber-400/10 dark:file:hover:bg-amber-300">
                        <p class="mt-2 text-xs text-slate-500 dark:text-zinc-400">JPG, PNG, or WebP. Maximum file size is 2MB.</p>
                        <x-input-error :messages="$errors->get('logo')" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="primary_color" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                                Primary Color <span class="text-rose-600">*</span>
                            </label>
                            <input id="primary_color"
                                   name="primary_color"
                                   type="color"
                                   value="{{ old('primary_color', '#d97706') }}"
                                   required
                                   class="h-12 w-full rounded-xl border border-slate-300 bg-white p-1 shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950">
                            <x-input-error :messages="$errors->get('primary_color')" class="mt-2" />
                        </div>

                        <div>
                            <label for="accent_color" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                                Accent Color <span class="text-rose-600">*</span>
                            </label>
                            <input id="accent_color"
                                   name="accent_color"
                                   type="color"
                                   value="{{ old('accent_color', '#fef3c7') }}"
                                   required
                                   class="h-12 w-full rounded-xl border border-slate-300 bg-white p-1 shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950">
                            <x-input-error :messages="$errors->get('accent_color')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <label for="site_logo" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">Site Identity Logo</label>
                        <input id="site_logo"
                               name="site_logo"
                               type="file"
                               accept="image/png,image/jpeg,image/webp,image/x-icon"
                               class="w-full cursor-pointer rounded-xl border border-slate-300 px-4 py-3 text-sm shadow-sm file:mr-4 file:cursor-pointer file:rounded-lg file:border-0 file:bg-zinc-950 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-amber-100 hover:border-amber-400 hover:bg-amber-50/40 file:hover:bg-amber-700 focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:file:bg-amber-400 dark:file:text-zinc-950 dark:hover:border-amber-400 dark:hover:bg-amber-400/10 dark:file:hover:bg-amber-300">
                        <p class="mt-2 text-xs text-slate-500 dark:text-zinc-400">Used for the browser tab/site icon. PNG, JPG, WebP, or ICO up to 1MB.</p>
                        <x-input-error :messages="$errors->get('site_logo')" class="mt-2" />
                    </div>

                    <button type="submit"
                            class="w-full rounded-xl bg-zinc-950 px-5 py-3 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 dark:bg-amber-400 dark:text-zinc-950">
                        Create Brand
                    </button>
                </form>
            </div>

            <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                <div class="border-b border-slate-200 px-6 py-4 dark:border-zinc-800">
                    <h2 class="font-semibold text-slate-900 dark:text-zinc-100">Brand Directory</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full table-fixed text-left text-xs">
                        <thead class="bg-slate-50 text-[11px] uppercase leading-tight text-slate-500 dark:bg-zinc-950 dark:text-zinc-400">
                            <tr>
                                <th class="w-[12%] px-5 py-4">Logo</th>
                                <th class="w-[20%] px-5 py-4">Imprint Name</th>
                                <th class="w-[14%] px-5 py-4">Theme</th>
                                <th class="w-[24%] px-5 py-4">Description</th>
                                <th class="w-[20%] px-5 py-4">Address</th>
                                <th class="w-[10%] px-5 py-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-zinc-800">
                            @forelse ($brands as $brand)
                                <tr x-data="{ editOpen: false }" class="align-top hover:bg-slate-50 dark:hover:bg-zinc-800/60">
                                    <td class="px-5 py-4">
                                        @if ($brand->logo_path)
                                            <img src="{{ asset('storage/' . $brand->logo_path) }}?v={{ $brand->updated_at?->timestamp }}"
                                                 alt="{{ $brand->imprint_name }}"
                                                 class="h-12 w-20 object-contain">
                                        @else
                                            <div class="flex h-12 w-20 items-center justify-center rounded-xl bg-slate-100 text-xs font-semibold text-slate-500 dark:bg-zinc-800 dark:text-zinc-400">
                                                No logo
                                            </div>
                                        @endif
                                    </td>
                                    <td class="break-words px-5 py-4">
                                        <p class="font-semibold text-slate-900 dark:text-zinc-100">{{ $brand->imprint_name }}</p>
                                        <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400">{{ $brand->crm_display_name ?: $brand->imprint_name . ' CRM' }}</p>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-2">
                                            <span class="h-6 w-6 rounded-full ring-1 ring-slate-200 dark:ring-zinc-700" style="background-color: {{ $brand->primary_color ?? '#d97706' }}"></span>
                                            <span class="h-6 w-6 rounded-full ring-1 ring-slate-200 dark:ring-zinc-700" style="background-color: {{ $brand->accent_color ?? '#fef3c7' }}"></span>
                                            @if ($brand->site_logo_path)
                                                <img src="{{ asset('storage/' . $brand->site_logo_path) }}?v={{ $brand->updated_at?->timestamp }}"
                                                     alt="{{ $brand->imprint_name }} site icon"
                                                     class="h-6 w-6 rounded object-contain ring-1 ring-slate-200 dark:ring-zinc-700">
                                            @endif
                                        </div>
                                    </td>
                                    <td class="break-words px-5 py-4 text-slate-600 dark:text-zinc-300">{{ $brand->description ?: '-' }}</td>
                                    <td class="break-words px-5 py-4 text-slate-600 dark:text-zinc-300">{{ $brand->address ?: '-' }}</td>
                                    <td class="px-5 py-4 text-right">
                                        <button type="button"
                                                x-on:click="editOpen = true"
                                                class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-amber-200 bg-amber-50 text-amber-700 shadow-sm hover:bg-amber-100 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-200"
                                                title="Edit brand">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 7.125 16.875 4.5" />
                                            </svg>
                                        </button>

                                        <div x-show="editOpen"
                                             x-cloak
                                             x-on:keydown.escape.window="editOpen = false"
                                             class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4 text-left">
                                            <form method="POST" action="{{ route('admin.brands.update', $brand) }}" enctype="multipart/form-data" class="flex max-h-[88vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-white shadow-xl dark:bg-zinc-900">
                                                @csrf
                                                @method('PUT')

                                                <div class="shrink-0 border-b border-slate-200 px-6 py-4 dark:border-zinc-800">
                                                <div class="flex items-start justify-between gap-4">
                                                    <div>
                                                        <h3 class="text-lg font-bold text-slate-900 dark:text-zinc-100">Edit Brand</h3>
                                                        <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Update imprint details.</p>
                                                    </div>
                                                    <button type="button" x-on:click="editOpen = false" class="text-slate-500 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-zinc-100">
                                                        <span class="sr-only">Close</span>
                                                        &times;
                                                    </button>
                                                </div>
                                                </div>

                                                <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-6 py-5">
                                                    <div>
                                                        <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                                                            Imprint Name <span class="text-rose-600">*</span>
                                                        </label>
                                                        <input name="imprint_name"
                                                               type="text"
                                                               value="{{ old('imprint_name', $brand->imprint_name) }}"
                                                               required
                                                               class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                                    </div>

                                                    <div>
                                                        <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">CRM Display Name</label>
                                                        <input name="crm_display_name"
                                                               type="text"
                                                               value="{{ old('crm_display_name', $brand->crm_display_name) }}"
                                                               placeholder="Example: Inkspire Media House CRM"
                                                               class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                                        <p class="mt-2 text-xs text-slate-500 dark:text-zinc-400">Shown in the browser tab after users log in.</p>
                                                    </div>

                                                    <div>
                                                        <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">Description</label>
                                                        <textarea name="description"
                                                                  rows="4"
                                                                  class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">{{ old('description', $brand->description) }}</textarea>
                                                    </div>

                                                    <div>
                                                        <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">Address</label>
                                                        <textarea name="address"
                                                                  rows="4"
                                                                  class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">{{ old('address', $brand->address) }}</textarea>
                                                    </div>

                                                    <div>
                                                        <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">Logo</label>
                                                        @if ($brand->logo_path)
                                                            <img src="{{ asset('storage/' . $brand->logo_path) }}?v={{ $brand->updated_at?->timestamp }}"
                                                                 alt="{{ $brand->imprint_name }}"
                                                                 class="mb-3 h-16 w-28 object-contain">
                                                        @endif
                                                        <input name="logo"
                                                               type="file"
                                                               accept="image/png,image/jpeg,image/webp"
                                                               class="w-full cursor-pointer rounded-xl border border-slate-300 px-4 py-3 text-sm shadow-sm file:mr-4 file:cursor-pointer file:rounded-lg file:border-0 file:bg-zinc-950 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-amber-100 hover:border-amber-400 hover:bg-amber-50/40 file:hover:bg-amber-700 focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:file:bg-amber-400 dark:file:text-zinc-950 dark:hover:border-amber-400 dark:hover:bg-amber-400/10 dark:file:hover:bg-amber-300">
                                                    </div>

                                                    <div class="grid grid-cols-2 gap-3">
                                                        <div>
                                                            <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                                                                Primary Color <span class="text-rose-600">*</span>
                                                            </label>
                                                            <input name="primary_color"
                                                                   type="color"
                                                                   value="{{ old('primary_color', $brand->primary_color ?? '#d97706') }}"
                                                                   required
                                                                   class="h-12 w-full rounded-xl border border-slate-300 bg-white p-1 shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950">
                                                        </div>

                                                        <div>
                                                            <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                                                                Accent Color <span class="text-rose-600">*</span>
                                                            </label>
                                                            <input name="accent_color"
                                                                   type="color"
                                                                   value="{{ old('accent_color', $brand->accent_color ?? '#fef3c7') }}"
                                                                   required
                                                                   class="h-12 w-full rounded-xl border border-slate-300 bg-white p-1 shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950">
                                                        </div>
                                                    </div>

                                                    <div>
                                                        <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">Site Identity Logo</label>
                                                        @if ($brand->site_logo_path)
                                                            <img src="{{ asset('storage/' . $brand->site_logo_path) }}?v={{ $brand->updated_at?->timestamp }}"
                                                                 alt="{{ $brand->imprint_name }} site icon"
                                                                 class="mb-3 h-10 w-10 rounded object-contain ring-1 ring-slate-200 dark:ring-zinc-700">
                                                        @endif
                                                        <input name="site_logo"
                                                               type="file"
                                                               accept="image/png,image/jpeg,image/webp,image/x-icon"
                                                               class="w-full cursor-pointer rounded-xl border border-slate-300 px-4 py-3 text-sm shadow-sm file:mr-4 file:cursor-pointer file:rounded-lg file:border-0 file:bg-zinc-950 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-amber-100 hover:border-amber-400 hover:bg-amber-50/40 file:hover:bg-amber-700 focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:file:bg-amber-400 dark:file:text-zinc-950 dark:hover:border-amber-400 dark:hover:bg-amber-400/10 dark:file:hover:bg-amber-300">
                                                    </div>
                                                </div>

                                                <div class="shrink-0 border-t border-slate-200 bg-white px-6 py-4 dark:border-zinc-800 dark:bg-zinc-900">
                                                <div class="flex justify-end gap-3">
                                                    <button type="button"
                                                            x-on:click="editOpen = false"
                                                            class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800">
                                                        Cancel
                                                    </button>
                                                    <button type="submit"
                                                            class="rounded-xl bg-zinc-950 px-5 py-3 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                                                        Save Brand
                                                    </button>
                                                </div>
                                                </div>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-16 text-center text-sm text-slate-500 dark:text-zinc-400">
                                        No brands yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($brands->hasPages())
                    <div class="border-t border-slate-200 px-5 py-3 dark:border-zinc-800">
                        {{ $brands->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
