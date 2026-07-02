<x-app-layout>
    <x-slot name="header">
        {{ $pageTitle }}
    </x-slot>

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">{{ $pageTitle }}</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">{{ $pageDescription }}</p>
        </div>

        <div class="rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
            <h2 class="text-lg font-bold text-slate-900 dark:text-zinc-100">{{ $pageTitle }} page</h2>
            <p class="mx-auto mt-2 max-w-xl text-sm text-slate-500 dark:text-zinc-400">
                This section is ready for the sales workflow.
            </p>
        </div>
    </div>
</x-app-layout>
