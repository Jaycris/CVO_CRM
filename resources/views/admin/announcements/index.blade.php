<x-app-layout>
    <x-slot name="header">
        Announcements
    </x-slot>

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">Announcements</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                Post company-wide announcements and optionally send them by email.
            </p>
        </div>

        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('success') }}</div>
        @endif

        @if (session('error'))
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">{{ session('error') }}</div>
        @endif

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-[28rem_1fr]">
            <form method="POST" action="{{ route('admin.announcements.store') }}" class="space-y-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                @csrf
                <div>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-zinc-100">Create Announcement</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">This will notify all active users inside the CRM.</p>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-200">Title <span class="text-rose-600">*</span></label>
                    <input name="title" value="{{ old('title') }}" required class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                    <x-input-error :messages="$errors->get('title')" class="mt-2" />
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-200">Message <span class="text-rose-600">*</span></label>
                    <textarea name="body" rows="7" required class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">{{ old('body') }}</textarea>
                    <x-input-error :messages="$errors->get('body')" class="mt-2" />
                </div>

                <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm dark:border-zinc-800 dark:bg-zinc-950">
                    <input type="checkbox" name="send_email" value="1" class="mt-1 rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                    <span>
                        <span class="block font-semibold text-slate-800 dark:text-zinc-100">Send email to all users</span>
                        <span class="mt-1 block text-slate-500 dark:text-zinc-400">Leave unchecked if this should only appear in CRM notifications.</span>
                    </span>
                </label>

                <button type="submit" class="w-full rounded-xl bg-zinc-950 px-5 py-3 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                    Post Announcement
                </button>
            </form>

            <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                <div class="border-b border-slate-200 px-6 py-4 dark:border-zinc-800">
                    <h2 class="font-semibold text-slate-900 dark:text-zinc-100">Announcement History</h2>
                </div>

                <div class="divide-y divide-slate-200 dark:divide-zinc-800">
                    @forelse ($announcements as $announcement)
                        <article class="px-6 py-5">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-bold text-slate-900 dark:text-zinc-100">{{ $announcement->title }}</h3>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400">
                                        Posted by {{ $announcement->creator?->first_name ?? 'Admin' }} {{ $announcement->creator?->last_name }}
                                        on {{ $announcement->published_at?->format('M d, Y h:i A') ?? $announcement->created_at?->format('M d, Y h:i A') }}
                                    </p>
                                </div>
                                <span class="rounded-full {{ $announcement->email_sent ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }} px-3 py-1 text-xs font-semibold">
                                    {{ $announcement->email_sent ? 'Email sent' : 'CRM only' }}
                                </span>
                            </div>
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
    </div>
</x-app-layout>
