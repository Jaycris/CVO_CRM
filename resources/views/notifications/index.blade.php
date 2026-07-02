<x-app-layout>
    <x-slot name="header">
        Notifications
    </x-slot>

    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">Notifications</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                    View all updates assigned to you.
                </p>
            </div>

            @if (auth()->user()->unreadNotifications()->exists())
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button type="submit"
                            class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-700 shadow-sm hover:bg-amber-100 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-200">
                        Mark all read
                    </button>
                </form>
            @endif
        </div>

        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
            <div class="border-b border-slate-200 px-6 py-4 dark:border-zinc-800">
                <h2 class="font-semibold text-slate-900 dark:text-zinc-100">Notification History</h2>
            </div>

            <div class="divide-y divide-slate-200 dark:divide-zinc-800">
                @forelse ($notifications as $notification)
                    <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                        @csrf
                        <button type="submit"
                                class="block w-full px-6 py-4 text-left hover:bg-slate-50 dark:hover:bg-zinc-800 {{ $notification->read_at ? 'bg-white dark:bg-zinc-900' : 'bg-amber-50/70 dark:bg-amber-400/10' }}">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="font-semibold text-slate-900 dark:text-zinc-100">
                                        {{ $notification->data['title'] ?? 'Notification' }}
                                    </p>
                                    @if (! empty($notification->data['message']))
                                        <p class="mt-1 text-sm text-slate-600 dark:text-zinc-300">
                                            {{ $notification->data['message'] }}
                                        </p>
                                    @endif
                                    <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                                        {{ $notification->data['author_name'] ?? 'Client' }}
                                        -
                                        {{ $notification->data['book_title'] ?? 'Untitled' }}
                                    </p>
                                    <p class="mt-2 text-xs text-slate-400 dark:text-zinc-500">
                                        {{ $notification->created_at?->diffForHumans() }}
                                    </p>
                                </div>

                                @unless ($notification->read_at)
                                    <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-rose-500"></span>
                                @endunless
                            </div>
                        </button>
                    </form>
                @empty
                    <div class="px-6 py-16 text-center text-sm text-slate-500 dark:text-zinc-400">
                        No notifications yet.
                    </div>
                @endforelse
            </div>

            @if ($notifications->hasPages())
                <div class="border-t border-slate-200 px-5 py-3 dark:border-zinc-800">
                    {{ $notifications->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
