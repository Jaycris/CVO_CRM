<x-app-layout>
    <x-slot name="header">
        Dashboard Banners
    </x-slot>

    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">Dashboard Banners</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                    Create hero banners for top sellers, events, and important dashboard messages.
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
                Please check the banner form and try again.
            </div>
        @endif

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-[27rem_1fr]" x-data="{ editing: null }">
            <form method="POST"
                  action="{{ route('admin.dashboard-banners.store') }}"
                  enctype="multipart/form-data"
                  class="space-y-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                @csrf

                <div>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-zinc-100">Create Banner</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Use this for celebrations, events, and dashboard presentations.</p>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                        Brand / Account <span class="text-rose-600">*</span>
                    </label>
                    <select name="brand_id" required class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        @foreach ($brands as $brand)
                            <option value="{{ $brand->id }}" @selected((string) old('brand_id', $defaultBrandId) === (string) $brand->id)>
                                {{ $brand->imprint_name }}
                            </option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('brand_id')" class="mt-2" />
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                        Banner Type <span class="text-rose-600">*</span>
                    </label>
                    <select name="type" required class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        @foreach ($types as $value => $label)
                            <option value="{{ $value }}" @selected(old('type', 'announcement') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('type')" class="mt-2" />
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                        Title <span class="text-rose-600">*</span>
                    </label>
                    <input name="title" value="{{ old('title') }}" required placeholder="Congratulations to our Top Seller"
                           class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                    <x-input-error :messages="$errors->get('title')" class="mt-2" />
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                        Message <span class="text-rose-600">*</span>
                    </label>
                    <textarea name="message" rows="5" required placeholder="Celebrate an achievement or announce an upcoming event."
                              class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">{{ old('message') }}</textarea>
                    <x-input-error :messages="$errors->get('message')" class="mt-2" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">Button Text</label>
                        <input name="button_text" value="{{ old('button_text') }}" placeholder="View Details"
                               class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">Button Link</label>
                        <input name="button_url" value="{{ old('button_url') }}" placeholder="https://..."
                               class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">Start</label>
                        <input type="datetime-local" name="starts_at" value="{{ old('starts_at') }}"
                               class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">End</label>
                        <input type="datetime-local" name="ends_at" value="{{ old('ends_at') }}"
                               class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">Banner Image</label>
                    <input type="file" name="image" accept="image/*"
                           class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm shadow-sm file:mr-4 file:rounded-lg file:border-0 file:bg-zinc-950 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-amber-100 hover:file:bg-black dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                    <p class="mt-2 text-xs text-slate-500 dark:text-zinc-400">Optional. JPG, PNG, WebP, or animated GIF. Recommended 1600 x 600 px. Maximum 8MB.</p>
                    <x-input-error :messages="$errors->get('image')" class="mt-2" />
                </div>

                <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm dark:border-zinc-800 dark:bg-zinc-950">
                    <input type="checkbox" name="is_active" value="1" checked class="mt-1 rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                    <span>
                        <span class="block font-semibold text-slate-800 dark:text-zinc-100">Active banner</span>
                        <span class="mt-1 block text-slate-500 dark:text-zinc-400">Show this on the dashboard when it is inside the schedule.</span>
                    </span>
                </label>

                <button type="submit" class="w-full rounded-xl bg-zinc-950 px-5 py-3 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                    Create Banner
                </button>
            </form>

            <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 px-6 py-4 dark:border-zinc-800">
                    <div>
                        <h2 class="font-semibold text-slate-900 dark:text-zinc-100">Banner Directory</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Active and scheduled dashboard banners.</p>
                    </div>

                    <form method="GET" action="{{ route('admin.dashboard-banners.index') }}" class="flex flex-wrap items-center gap-3">
                        @if (\App\Support\BrandScope::canAccessAllBrands(request()->user()))
                            <select name="brand_id" class="rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                <option value="">All brands</option>
                                @foreach ($brands as $brand)
                                    <option value="{{ $brand->id }}" @selected((string) $brandFilter === (string) $brand->id)>{{ $brand->imprint_name }}</option>
                                @endforeach
                            </select>
                        @endif

                        <input name="search" value="{{ $search }}" placeholder="Search banners..."
                               class="w-56 rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        <button class="rounded-xl bg-zinc-950 px-5 py-3 text-sm font-semibold text-amber-100 hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                            Search
                        </button>
                    </form>
                </div>

                <div class="divide-y divide-slate-200 dark:divide-zinc-800">
                    @forelse ($banners as $banner)
                        <article class="px-6 py-5">
                            @php
                                $bannerStatus = match (true) {
                                    ! $banner->is_active => ['label' => 'Inactive', 'class' => 'bg-slate-100 text-slate-600'],
                                    $banner->starts_at && $banner->starts_at->isFuture() => ['label' => 'Scheduled', 'class' => 'bg-sky-100 text-sky-700'],
                                    $banner->ends_at && $banner->ends_at->isPast() => ['label' => 'Expired', 'class' => 'bg-rose-100 text-rose-700'],
                                    default => ['label' => 'Visible', 'class' => 'bg-emerald-100 text-emerald-700'],
                                };
                            @endphp
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="font-bold text-slate-900 dark:text-zinc-100">{{ $banner->title }}</h3>
                                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">{{ $types[$banner->type] ?? 'Announcement' }}</span>
                                        <span class="rounded-full {{ $bannerStatus['class'] }} px-3 py-1 text-xs font-semibold">
                                            {{ $bannerStatus['label'] }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400">
                                        {{ $banner->brand?->imprint_name }} | {{ $banner->starts_at?->format('M d, Y h:i A') ?? 'No start date' }} - {{ $banner->ends_at?->format('M d, Y h:i A') ?? 'No end date' }}
                                    </p>
                                    <p class="mt-3 line-clamp-2 text-sm leading-6 text-slate-600 dark:text-zinc-300">{{ $banner->message }}</p>
                                </div>

                                <div class="flex shrink-0 items-center gap-2">
                                    <button type="button"
                                            x-on:click="editing = editing === {{ $banner->id }} ? null : {{ $banner->id }}"
                                            class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-700 hover:bg-amber-100 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-200">
                                        Edit
                                    </button>
                                    <form method="POST" action="{{ route('admin.dashboard-banners.destroy', $banner) }}" onsubmit="return confirm('Remove this dashboard banner?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-100 dark:border-rose-400/30 dark:bg-rose-400/10 dark:text-rose-200">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <form method="POST"
                                  action="{{ route('admin.dashboard-banners.update', $banner) }}"
                                  enctype="multipart/form-data"
                                  x-show="editing === {{ $banner->id }}"
                                  x-cloak
                                  class="mt-5 grid grid-cols-1 gap-4 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-zinc-800 dark:bg-zinc-950 md:grid-cols-2">
                                @csrf
                                @method('PUT')

                                <div>
                                    <label class="mb-2 block text-xs font-semibold uppercase text-slate-500 dark:text-zinc-400">Brand</label>
                                    <select name="brand_id" required class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                                        @foreach ($brands as $brand)
                                            <option value="{{ $brand->id }}" @selected($banner->brand_id === $brand->id)>{{ $brand->imprint_name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="mb-2 block text-xs font-semibold uppercase text-slate-500 dark:text-zinc-400">Type</label>
                                    <select name="type" required class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                                        @foreach ($types as $value => $label)
                                            <option value="{{ $value }}" @selected($banner->type === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="md:col-span-2">
                                    <label class="mb-2 block text-xs font-semibold uppercase text-slate-500 dark:text-zinc-400">Title</label>
                                    <input name="title" value="{{ $banner->title }}" required class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                                </div>

                                <div class="md:col-span-2">
                                    <label class="mb-2 block text-xs font-semibold uppercase text-slate-500 dark:text-zinc-400">Message</label>
                                    <textarea name="message" rows="4" required class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">{{ $banner->message }}</textarea>
                                </div>

                                <div>
                                    <label class="mb-2 block text-xs font-semibold uppercase text-slate-500 dark:text-zinc-400">Button Text</label>
                                    <input name="button_text" value="{{ $banner->button_text }}" class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                                </div>

                                <div>
                                    <label class="mb-2 block text-xs font-semibold uppercase text-slate-500 dark:text-zinc-400">Button Link</label>
                                    <input name="button_url" value="{{ $banner->button_url }}" class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                                </div>

                                <div>
                                    <label class="mb-2 block text-xs font-semibold uppercase text-slate-500 dark:text-zinc-400">Start</label>
                                    <input type="datetime-local" name="starts_at" value="{{ $banner->starts_at?->format('Y-m-d\TH:i') }}" class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                                </div>

                                <div>
                                    <label class="mb-2 block text-xs font-semibold uppercase text-slate-500 dark:text-zinc-400">End</label>
                                    <input type="datetime-local" name="ends_at" value="{{ $banner->ends_at?->format('Y-m-d\TH:i') }}" class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                                </div>

                                <div class="md:col-span-2">
                                    <label class="mb-2 block text-xs font-semibold uppercase text-slate-500 dark:text-zinc-400">Replace Image</label>
                                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm shadow-sm file:mr-4 file:rounded-lg file:border-0 file:bg-zinc-950 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-amber-100 hover:file:bg-black dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                                </div>

                                <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 text-sm dark:border-zinc-800 dark:bg-zinc-900">
                                    <input type="checkbox" name="is_active" value="1" @checked($banner->is_active) class="rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                                    <span class="font-semibold text-slate-800 dark:text-zinc-100">Active banner</span>
                                </label>

                                <div class="flex items-center justify-end gap-3">
                                    <button type="button" x-on:click="editing = null" class="px-4 py-2 text-sm font-semibold text-slate-600 dark:text-zinc-300">
                                        Cancel
                                    </button>
                                    <button type="submit" class="rounded-xl bg-zinc-950 px-5 py-3 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                                        Save Banner
                                    </button>
                                </div>
                            </form>
                        </article>
                    @empty
                        <div class="px-6 py-16 text-center text-sm text-slate-500 dark:text-zinc-400">No dashboard banners yet.</div>
                    @endforelse
                </div>

                @if ($banners->hasPages())
                    <div class="border-t border-slate-200 px-5 py-3 dark:border-zinc-800">{{ $banners->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
