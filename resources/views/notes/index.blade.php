<x-app-layout>
    <x-slot name="header">
        Notes
    </x-slot>

    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">Notes</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Keep personal notes for your own work.</p>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200">
                {{ session('success') }}
            </div>
        @endif

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-[380px_1fr]">
            <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                <h2 class="text-lg font-bold text-slate-900 dark:text-zinc-100">Add Note</h2>

                <form method="POST" action="{{ route('notes.store') }}" class="mt-5 space-y-4">
                    @csrf

                    <div>
                        <label for="title" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                            Title <span class="text-rose-500">*</span>
                        </label>
                        <input id="title"
                               name="title"
                               value="{{ old('title') }}"
                               required
                               class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        <x-input-error :messages="$errors->get('title')" class="mt-2" />
                    </div>

                    <div>
                        <label for="body" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                            Note <span class="text-rose-500">*</span>
                        </label>
                        <textarea id="body"
                                  name="body"
                                  rows="8"
                                  required
                                  class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">{{ old('body') }}</textarea>
                        <x-input-error :messages="$errors->get('body')" class="mt-2" />
                    </div>

                    <button type="submit"
                            class="w-full rounded-xl bg-zinc-950 px-5 py-3 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                        Add Note
                    </button>
                </form>
            </section>

            <section class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                <div class="border-b border-slate-200 px-6 py-4 dark:border-zinc-800">
                    <h2 class="font-semibold text-slate-900 dark:text-zinc-100">My Notes</h2>
                </div>

                @if ($notes->count())
                    <div class="grid grid-cols-1 gap-4 p-6 lg:grid-cols-2">
                        @foreach ($notes as $note)
                            <article class="rounded-2xl border border-slate-200 bg-slate-50 p-5 dark:border-zinc-800 dark:bg-zinc-950"
                                     x-data="{ editing: false }">
                                <div x-show="!editing">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <h3 class="font-bold text-slate-900 dark:text-zinc-100">{{ $note->title }}</h3>
                                            <p class="mt-1 text-xs text-slate-500 dark:text-zinc-500">{{ $note->updated_at->format('M d, Y h:i A') }}</p>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <button type="button"
                                                    x-on:click="editing = true"
                                                    title="Edit note"
                                                    class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-200">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
                                                </svg>
                                            </button>

                                            <form method="POST" action="{{ route('notes.destroy', $note) }}" x-on:submit="if (!confirm('Delete this note?')) $event.preventDefault();">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        title="Delete note"
                                                        class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100 dark:border-rose-400/30 dark:bg-rose-400/10 dark:text-rose-200">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166M19.228 5.79 18.16 19.673A2.25 2.25 0 0 1 15.916 21.75H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .563c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    </div>

                                    <p class="mt-4 whitespace-pre-line text-sm leading-6 text-slate-600 dark:text-zinc-300">{{ $note->body }}</p>
                                </div>

                                <form x-show="editing" x-cloak method="POST" action="{{ route('notes.update', $note) }}" class="space-y-4">
                                    @csrf
                                    @method('PUT')

                                    <input name="title"
                                           value="{{ old('title', $note->title) }}"
                                           required
                                           class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">

                                    <textarea name="body"
                                              rows="8"
                                              required
                                              class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">{{ old('body', $note->body) }}</textarea>

                                    <div class="flex items-center justify-end gap-2">
                                        <button type="button"
                                                x-on:click="editing = false"
                                                class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800">
                                            Cancel
                                        </button>
                                        <button type="submit"
                                                class="rounded-xl bg-zinc-950 px-4 py-2 text-sm font-semibold text-amber-100 hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                                            Save
                                        </button>
                                    </div>
                                </form>
                            </article>
                        @endforeach
                    </div>

                    <div class="border-t border-slate-200 px-6 py-4 dark:border-zinc-800">
                        {{ $notes->links() }}
                    </div>
                @else
                    <div class="flex min-h-72 flex-col items-center justify-center px-6 py-16 text-center">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-100 text-amber-700 dark:bg-amber-400/15 dark:text-amber-200">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 3.75h7.086a2.25 2.25 0 0 1 1.591.659l2.414 2.414a2.25 2.25 0 0 1 .659 1.591V20.25H7.5A2.25 2.25 0 0 1 5.25 18V6A2.25 2.25 0 0 1 7.5 3.75Z" />
                            </svg>
                        </div>
                        <h3 class="mt-4 font-semibold text-slate-900 dark:text-zinc-100">No notes yet</h3>
                        <p class="mt-2 max-w-md text-sm text-slate-500 dark:text-zinc-400">Add your first note to keep reminders, client details, or quick thoughts in one place.</p>
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
