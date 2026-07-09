<x-app-layout>
    <x-slot name="header">
        Services
    </x-slot>

    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">Services</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                    Manage service templates, inclusions, prices, and brand availability.
                </p>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 dark:border-rose-400/30 dark:bg-rose-400/10 dark:text-rose-200">
                Please check the service form and try again.
            </div>
        @endif

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-[25rem_1fr]">
            <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800"
                 x-data="serviceForm({ inclusions: @js(old('inclusions', [['name' => '']])) })">
                <h2 class="text-lg font-bold text-slate-900 dark:text-zinc-100">Create Service</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Add a service for one brand/account.</p>

                <form method="POST" action="{{ route('admin.services.store') }}" enctype="multipart/form-data" class="mt-5 space-y-4">
                    @csrf

                    <div>
                        <label for="brand_id" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                            Brand / Account <span class="text-rose-600">*</span>
                        </label>
                        <select id="brand_id"
                                name="brand_id"
                                required
                                class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                            @foreach ($brands as $brand)
                                <option value="{{ $brand->id }}" @selected((string) old('brand_id', $defaultBrandId) === (string) $brand->id)>
                                    {{ $brand->imprint_name }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('brand_id')" class="mt-2" />
                    </div>

                    <div>
                        <label for="name" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                            Service Name <span class="text-rose-600">*</span>
                        </label>
                        <input id="name"
                               name="name"
                               type="text"
                               value="{{ old('name') }}"
                               required
                               class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="category" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                                Category <span class="text-rose-600">*</span>
                            </label>
                            <select id="category"
                                    name="category"
                                    required
                                    class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                @foreach (['Publishing', 'Marketing', 'Events'] as $category)
                                    <option value="{{ $category }}" @selected(old('category') === $category)>{{ $category }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('category')" class="mt-2" />
                        </div>

                        <div>
                            <label for="price" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">Price</label>
                            <input id="price"
                                   name="price"
                                   type="number"
                                   min="0"
                                   step="0.01"
                                   value="{{ old('price') }}"
                                   class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                            <x-input-error :messages="$errors->get('price')" class="mt-2" />
                        </div>
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
                        <label for="pdf_file" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">Service File</label>
                        <input id="pdf_file"
                               name="pdf_file"
                               type="file"
                               accept="application/pdf,.pdf,image/jpeg,image/png,image/webp,image/gif,.jpg,.jpeg,.png,.webp,.gif"
                               class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm shadow-sm file:mr-4 file:rounded-lg file:border-0 file:bg-zinc-950 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-amber-100 hover:file:bg-black focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:file:bg-amber-400 dark:file:text-zinc-950">
                        <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400">PDF, JPG, PNG, WebP, or GIF. Maximum file size is 10MB.</p>
                        <x-input-error :messages="$errors->get('pdf_file')" class="mt-2" />
                    </div>

                    <div class="rounded-xl border border-slate-200 p-4 dark:border-zinc-800">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 dark:text-zinc-100">Inclusions</h3>
                                <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400">Add each work item included in this service.</p>
                            </div>
                            <button type="button"
                                    x-on:click="addInclusion()"
                                    class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700 hover:bg-amber-100 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-200">
                                + Add
                            </button>
                        </div>

                        <div class="mt-4 space-y-3">
                            <template x-for="(inclusion, index) in inclusions" :key="index">
                                <div class="rounded-xl bg-slate-50 p-3 dark:bg-zinc-950">
                                    <div class="grid grid-cols-1 gap-3">
                                        <div>
                                            <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-zinc-400">Inclusion</label>
                                            <input type="text"
                                                   x-bind:name="`inclusions[${index}][name]`"
                                                   x-model="inclusion.name"
                                                   placeholder="Example: Cover design"
                                                   class="w-full rounded-lg border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                                        </div>
                                        <div class="flex justify-end">
                                            <button type="button"
                                                    x-on:click="removeInclusion(index)"
                                                    x-show="inclusions.length > 1"
                                                    class="text-xs font-semibold text-rose-600 hover:text-rose-700 dark:text-rose-300">
                                                Remove
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <button type="submit"
                            class="w-full rounded-xl bg-zinc-950 px-5 py-3 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 dark:bg-amber-400 dark:text-zinc-950">
                        Create Service
                    </button>
                </form>
            </div>

            <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 px-6 py-4 dark:border-zinc-800">
                    <div>
                        <h2 class="font-semibold text-slate-900 dark:text-zinc-100">Service Directory</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Services are visible only to their selected brand/account.</p>
                    </div>

                    <form method="GET" action="{{ route('admin.services.index') }}" class="flex flex-wrap items-center gap-3">
                        @if (\App\Support\BrandScope::canAccessAllBrands(request()->user()))
                            <select name="brand_id"
                                    class="w-48 rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                <option value="">All brands</option>
                                @foreach ($brands as $brand)
                                    <option value="{{ $brand->id }}" @selected((string) $brandFilter === (string) $brand->id)>{{ $brand->imprint_name }}</option>
                                @endforeach
                            </select>
                        @endif
                        <input type="search"
                               name="search"
                               value="{{ $search }}"
                               placeholder="Search services..."
                               class="w-64 rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        <button type="submit"
                                class="rounded-xl bg-zinc-950 px-5 py-3 text-sm font-semibold text-amber-100 hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                            Search
                        </button>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full table-fixed text-left text-xs">
                        <thead class="bg-slate-50 text-[11px] uppercase leading-tight text-slate-500 dark:bg-zinc-950 dark:text-zinc-400">
                            <tr>
                                <th class="w-[17%] px-5 py-4">Service</th>
                                <th class="w-[13%] px-5 py-4">Category</th>
                                <th class="w-[13%] px-5 py-4">Brand</th>
                                <th class="w-[10%] px-5 py-4">Price</th>
                                <th class="w-[27%] px-5 py-4">Inclusions</th>
                                <th class="w-[12%] px-5 py-4">Created</th>
                                <th class="w-[8%] px-5 py-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-zinc-800">
                            @forelse ($services as $service)
                                <tr x-data="{ editOpen: false }" class="align-top hover:bg-slate-50 dark:hover:bg-zinc-800/60">
                                    <td class="break-words px-5 py-4">
                                        <p class="font-semibold text-slate-900 dark:text-zinc-100">{{ $service->name }}</p>
                                        <p class="mt-1 line-clamp-2 text-xs leading-5 text-slate-500 dark:text-zinc-400">{{ $service->description ?: 'No description' }}</p>
                                        @if ($service->pdf_path)
                                            <a href="{{ asset('storage/' . $service->pdf_path) }}"
                                               target="_blank"
                                               class="mt-2 inline-flex text-xs font-semibold text-[var(--brand-primary)] hover:underline">
                                                View File
                                            </a>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">
                                            {{ $service->category }}
                                        </span>
                                    </td>
                                    <td class="break-words px-5 py-4">
                                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                                              style="background-color: {{ $service->brand?->accent_color ?? '#d1fae5' }}; color: {{ $service->brand?->primary_color ?? '#065f46' }};">
                                            {{ $service->brand?->imprint_name ?? 'CreatiVision' }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 font-semibold text-slate-900 dark:text-zinc-100">
                                        {{ is_null($service->price) ? '-' : '$' . number_format((float) $service->price, 2) }}
                                    </td>
                                    <td class="px-5 py-4">
                                        @if ($service->inclusions->isNotEmpty())
                                            <div class="space-y-2">
                                                @foreach ($service->inclusions->take(3) as $inclusion)
                                                    <div class="rounded-lg bg-slate-50 px-3 py-2 dark:bg-zinc-950">
                                                        <p class="font-semibold text-slate-800 dark:text-zinc-100">{{ $inclusion->name }}</p>
                                                    </div>
                                                @endforeach
                                                @if ($service->inclusions->count() > 3)
                                                    <p class="text-xs font-semibold text-slate-500 dark:text-zinc-400">+ {{ $service->inclusions->count() - 3 }} more</p>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-slate-400 dark:text-zinc-500">No inclusions yet</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-slate-600 dark:text-zinc-300">{{ $service->created_at?->format('M d, Y') }}</td>
                                    <td class="px-5 py-4 text-right">
                                        <div class="inline-flex items-center gap-2">
                                            <button type="button"
                                                    x-on:click="editOpen = true"
                                                    class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-amber-200 bg-amber-50 text-amber-700 shadow-sm hover:bg-amber-100 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-200"
                                                    title="Edit service">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 7.125 16.875 4.5" />
                                                </svg>
                                            </button>

                                            <form method="POST" action="{{ route('admin.services.destroy', $service) }}" onsubmit="return confirm('Delete this service?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-rose-200 bg-rose-50 text-rose-600 shadow-sm hover:bg-rose-100 dark:border-rose-400/30 dark:bg-rose-400/10 dark:text-rose-200"
                                                        title="Delete service">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673A2.25 2.25 0 0 1 15.916 21H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>

                                        <div x-show="editOpen"
                                             x-cloak
                                             x-on:keydown.escape.window="editOpen = false"
                                             class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4 text-left">
                                            <form method="POST"
                                                  action="{{ route('admin.services.update', $service) }}"
                                                  enctype="multipart/form-data"
                                                  x-data="serviceForm({ inclusions: @js($service->inclusions->map(fn ($inclusion) => ['name' => $inclusion->name])->values()->all() ?: [['name' => '']]) })"
                                                  class="flex max-h-[88vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-xl dark:bg-zinc-900">
                                                @csrf
                                                @method('PUT')

                                                <div class="shrink-0 border-b border-slate-200 px-6 py-4 dark:border-zinc-800">
                                                    <div class="flex items-start justify-between gap-4">
                                                        <div>
                                                            <h3 class="text-lg font-bold text-slate-900 dark:text-zinc-100">Edit Service</h3>
                                                            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Update service details and inclusions.</p>
                                                        </div>
                                                        <button type="button" x-on:click="editOpen = false" class="text-slate-500 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-zinc-100">
                                                            <span class="sr-only">Close</span>
                                                            &times;
                                                        </button>
                                                    </div>
                                                </div>

                                                <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-6 py-5">
                                                    <div>
                                                        <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">Brand / Account <span class="text-rose-600">*</span></label>
                                                        <select name="brand_id"
                                                                required
                                                                class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                                            @foreach ($brands as $brand)
                                                                <option value="{{ $brand->id }}" @selected((string) old('brand_id', $service->brand_id) === (string) $brand->id)>
                                                                    {{ $brand->imprint_name }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>

                                                    <div>
                                                        <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">Service Name <span class="text-rose-600">*</span></label>
                                                        <input name="name"
                                                               type="text"
                                                               value="{{ old('name', $service->name) }}"
                                                               required
                                                               class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                                    </div>

                                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                                        <div>
                                                            <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">Category <span class="text-rose-600">*</span></label>
                                                            <select name="category"
                                                                    required
                                                                    class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                                                @foreach (['Publishing', 'Marketing', 'Events'] as $category)
                                                                    <option value="{{ $category }}" @selected(old('category', $service->category) === $category)>{{ $category }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>

                                                        <div>
                                                            <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">Price</label>
                                                            <input name="price"
                                                                   type="number"
                                                                   min="0"
                                                                   step="0.01"
                                                                   value="{{ old('price', $service->price) }}"
                                                                   class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                                        </div>
                                                    </div>

                                                    <div>
                                                        <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">Description</label>
                                                        <textarea name="description"
                                                                  rows="4"
                                                                  class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">{{ old('description', $service->description) }}</textarea>
                                                    </div>

                                                    <div>
                                                        <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">Service File</label>
                                                        @if ($service->pdf_path)
                                                            <a href="{{ asset('storage/' . $service->pdf_path) }}"
                                                               target="_blank"
                                                               class="mb-2 inline-flex text-xs font-semibold text-[var(--brand-primary)] hover:underline">
                                                                Current File
                                                            </a>
                                                        @endif
                                                        <input name="pdf_file"
                                                               type="file"
                                                               accept="application/pdf,.pdf,image/jpeg,image/png,image/webp,image/gif,.jpg,.jpeg,.png,.webp,.gif"
                                                               class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm shadow-sm file:mr-4 file:rounded-lg file:border-0 file:bg-zinc-950 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-amber-100 hover:file:bg-black focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:file:bg-amber-400 dark:file:text-zinc-950">
                                                        <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400">Upload a new PDF or image only if you want to replace the current file.</p>
                                                    </div>

                                                    <div class="rounded-xl border border-slate-200 p-4 dark:border-zinc-800">
                                                        <div class="flex items-center justify-between gap-3">
                                                            <h4 class="text-sm font-bold text-slate-900 dark:text-zinc-100">Inclusions</h4>
                                                            <button type="button"
                                                                    x-on:click="addInclusion()"
                                                                    class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700 hover:bg-amber-100 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-200">
                                                                + Add
                                                            </button>
                                                        </div>

                                                        <div class="mt-4 space-y-3">
                                                            <template x-for="(inclusion, index) in inclusions" :key="index">
                                                                <div class="rounded-xl bg-slate-50 p-3 dark:bg-zinc-950">
                                                                    <div class="grid grid-cols-1 gap-3 md:grid-cols-[1fr_auto] md:items-end">
                                                                        <div>
                                                                            <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-zinc-400">Inclusion</label>
                                                                            <input type="text"
                                                                                   x-bind:name="`inclusions[${index}][name]`"
                                                                                   x-model="inclusion.name"
                                                                                   class="w-full rounded-lg border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                                                                        </div>
                                                                        <button type="button"
                                                                                x-on:click="removeInclusion(index)"
                                                                                x-show="inclusions.length > 1"
                                                                                class="rounded-lg px-3 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-400/10">
                                                                            Remove
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </template>
                                                        </div>
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
                                                            Save Service
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-16 text-center text-sm text-slate-500 dark:text-zinc-400">
                                        No services yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($services->hasPages())
                    <div class="border-t border-slate-200 px-5 py-3 dark:border-zinc-800">
                        {{ $services->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        function serviceForm(config = {}) {
            const inclusions = Array.isArray(config.inclusions) && config.inclusions.length
                ? config.inclusions
                : [{ name: '' }];

            return {
                inclusions,
                addInclusion() {
                    this.inclusions.push({ name: '' });
                },
                removeInclusion(index) {
                    this.inclusions.splice(index, 1);

                    if (this.inclusions.length === 0) {
                        this.addInclusion();
                    }
                },
            };
        }
    </script>
</x-app-layout>
