<x-app-layout>
    <x-slot name="header">
        Announcements
    </x-slot>

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">Announcements</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                Company-wide updates and notices.
            </p>
        </div>

        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
            <div class="divide-y divide-slate-200 dark:divide-zinc-800">
                @forelse ($announcements as $announcement)
                    <article class="px-6 py-5">
                        <h2 class="text-lg font-bold text-slate-900 dark:text-zinc-100">{{ $announcement->title }}</h2>
                        <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400">
                            {{ $announcement->published_at?->format('M d, Y h:i A') ?? $announcement->created_at?->format('M d, Y h:i A') }}
                        </p>
                        <p class="mt-4 whitespace-pre-line text-sm leading-6 text-slate-600 dark:text-zinc-300">{{ $announcement->body }}</p>
                    </article>
                @empty
                    <div class="px-6 py-16 text-center text-sm text-slate-500 dark:text-zinc-400">No announcements yet.</div>
                @endforelse
            </div>

            @if ($announcements->hasPages())
                <div class="border-t border-slate-200 px-5 py-3 dark:border-zinc-800">{{ $announcements->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
