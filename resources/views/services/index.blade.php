<x-app-layout>
    <x-slot name="header">
        Services
    </x-slot>

    <div class="space-y-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">Services Catalog</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                    Browse available company services, pricing, descriptions, and included work items.
                </p>
            </div>
        </div>

        <section class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="border-b border-slate-200 p-5 dark:border-zinc-800">
                <form method="GET" action="{{ route('services.index') }}" class="grid gap-3 lg:grid-cols-[1fr_auto_auto_auto]">
                    <input
                        type="search"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Search service, inclusion, brand..."
                        class="h-12 rounded-xl border-slate-300 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100"
                    >

                    <select
                        name="category"
                        class="h-12 rounded-xl border-slate-300 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100"
                    >
                        <option value="">All categories</option>
                        @foreach ($categories as $serviceCategory)
                            <option value="{{ $serviceCategory }}" @selected($category === $serviceCategory)>{{ $serviceCategory }}</option>
                        @endforeach
                    </select>

                    @if ($canFilterBrands)
                        <select
                            name="brand_id"
                            class="h-12 rounded-xl border-slate-300 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100"
                        >
                            <option value="">All brands</option>
                            @foreach ($brands as $brand)
                                <option value="{{ $brand->id }}" @selected((string) $brandFilter === (string) $brand->id)>{{ $brand->imprint_name }}</option>
                            @endforeach
                        </select>
                    @endif

                    <button type="submit" class="h-12 rounded-xl px-6 text-sm font-semibold text-white shadow-sm transition hover:brightness-95" style="background-color: var(--brand-primary);">
                        Search
                    </button>
                </form>
            </div>

            <div class="divide-y divide-slate-200 dark:divide-zinc-800">
                @forelse ($services as $service)
                    <article class="p-5">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-lg font-bold text-slate-900 dark:text-zinc-100">{{ $service->name }}</h2>
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold" style="background-color: var(--brand-accent); color: var(--brand-primary);">
                                        {{ $service->category }}
                                    </span>
                                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">
                                        {{ $service->brand?->imprint_name ?? 'No brand' }}
                                    </span>
                                </div>

                                @if ($service->description)
                                    <p class="mt-3 max-w-4xl text-sm leading-6 text-slate-600 dark:text-zinc-300">
                                        {{ $service->description }}
                                    </p>
                                @else
                                    <p class="mt-3 text-sm text-slate-400 dark:text-zinc-500">No description added yet.</p>
                                @endif
                            </div>

                            <div class="shrink-0 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-left dark:border-zinc-800 dark:bg-zinc-950 lg:text-right">
                                <p class="text-xs font-semibold uppercase text-slate-400 dark:text-zinc-500">Price</p>
                                <p class="mt-1 text-xl font-bold text-slate-900 dark:text-zinc-100">
                                    {{ is_null($service->price) ? 'TBD' : '$' . number_format((float) $service->price, 2) }}
                                </p>
                                @if ($service->pdf_path)
                                    <div class="mt-3 flex flex-wrap gap-2 lg:justify-end">
                                        <a href="{{ asset('storage/' . $service->pdf_path) }}"
                                           target="_blank"
                                           class="rounded-lg px-3 py-2 text-xs font-semibold text-white transition hover:brightness-95"
                                           style="background-color: var(--brand-primary);">
                                            View File
                                        </a>
                                        <a href="{{ asset('storage/' . $service->pdf_path) }}"
                                           download
                                           class="rounded-lg border px-3 py-2 text-xs font-semibold transition hover:bg-slate-100 dark:hover:bg-zinc-800"
                                           style="border-color: var(--brand-primary); color: var(--brand-primary);">
                                            Download
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="mt-5">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-zinc-500">Inclusions</p>
                            @if ($service->inclusions->isNotEmpty())
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach ($service->inclusions as $inclusion)
                                        <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm font-medium text-slate-700 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-300">
                                            {{ $inclusion->name }}
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <p class="mt-2 text-sm text-slate-400 dark:text-zinc-500">No inclusions added yet.</p>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="p-12 text-center">
                        <p class="font-semibold text-slate-900 dark:text-zinc-100">No services found</p>
                        <p class="mt-2 text-sm text-slate-500 dark:text-zinc-400">Try a different search or filter.</p>
                    </div>
                @endforelse
            </div>

            @if ($services->hasPages())
                <div class="border-t border-slate-200 px-5 py-4 dark:border-zinc-800">
                    {{ $services->links() }}
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
