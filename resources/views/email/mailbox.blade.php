<x-app-layout>
    <x-slot name="header">
        Email
    </x-slot>

    <div class="space-y-6" x-data="{ composeOpen: @js($errors->has('to') || $errors->has('subject') || $errors->has('body')), settingsOpen: @js($errors->has('email_address') || ! $account) }">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">Email</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                    Connect SiteGround mailboxes and send client email from the CRM.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if ($account)
                    <form method="POST" action="{{ route('email.accounts.sync', $account) }}">
                        @csrf
                        <button type="submit" class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">
                            Sync
                        </button>
                    </form>
                    <button type="button" x-on:click="composeOpen = true" class="inline-flex h-11 items-center justify-center rounded-xl bg-zinc-950 px-5 text-sm font-semibold text-amber-100 shadow-sm hover:bg-zinc-800 dark:bg-amber-400 dark:text-zinc-950">
                        Compose
                    </button>
                @endif
                <button type="button" x-on:click="settingsOpen = true" class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">
                    Mail Settings
                </button>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 dark:border-rose-400/30 dark:bg-rose-400/10 dark:text-rose-200">
                {{ session('error') }}
            </div>
        @endif

        @if (! $imapAvailable)
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-100">
                Inbox receiving needs the PHP IMAP extension enabled on the server. SMTP sending and account setup are available now.
            </div>
        @endif

        <div class="grid min-h-[42rem] grid-cols-1 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800 xl:grid-cols-[18rem_minmax(18rem,24rem)_1fr]">
            <aside class="border-b border-slate-200 bg-slate-50/80 p-4 dark:border-zinc-800 dark:bg-zinc-950/50 xl:border-b-0 xl:border-r">
                <div class="space-y-3">
                    <label for="email_account" class="text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-zinc-400">Mailbox</label>
                    <select id="email_account"
                            onchange="if (this.value) window.location.href = this.value"
                            class="w-full rounded-xl border-slate-200 bg-white text-sm font-semibold text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100">
                        @forelse ($accounts as $mailAccount)
                            <option value="{{ route('email.index', ['account' => $mailAccount->id, 'folder' => $folder]) }}" @selected($account?->id === $mailAccount->id)>
                                {{ $mailAccount->display_name }}
                            </option>
                        @empty
                            <option>No mailbox connected</option>
                        @endforelse
                    </select>
                </div>

                <nav class="mt-6 space-y-1">
                    @foreach (['INBOX' => 'Inbox', 'Sent' => 'Sent'] as $folderKey => $folderLabel)
                        <a href="{{ $account ? route('email.index', ['account' => $account->id, 'folder' => $folderKey]) : '#' }}"
                           class="{{ $folder === $folderKey ? 'bg-white text-slate-950 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:text-zinc-100 dark:ring-zinc-800' : 'text-slate-600 hover:bg-white/70 dark:text-zinc-300 dark:hover:bg-zinc-900/70' }} flex items-center justify-between rounded-xl px-3 py-2 text-sm font-semibold">
                            <span>{{ $folderLabel }}</span>
                            @if ($account)
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500 dark:bg-zinc-800 dark:text-zinc-400">
                                    {{ $account->messages()->where('folder', $folderKey)->count() }}
                                </span>
                            @endif
                        </a>
                    @endforeach
                </nav>

                @if ($account)
                    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-4 text-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <p class="font-semibold text-slate-900 dark:text-zinc-100">{{ $account->email_address }}</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400">
                            {{ $account->is_shared ? 'Brand mailbox' : 'Personal mailbox' }}
                        </p>
                        <p class="mt-3 text-xs text-slate-500 dark:text-zinc-400">
                            Last sync: {{ $account->last_synced_at?->format('m/d/Y @ h:i A') ?? 'Not synced yet' }}
                        </p>
                    </div>
                @endif
            </aside>

            <section class="border-b border-slate-200 dark:border-zinc-800 xl:border-b-0 xl:border-r">
                @if (! $account)
                    <div class="flex h-full min-h-[20rem] items-center justify-center p-8 text-center">
                        <div>
                            <h2 class="text-lg font-bold text-slate-900 dark:text-zinc-100">Connect your first mailbox</h2>
                            <p class="mt-2 text-sm text-slate-500 dark:text-zinc-400">Use the SiteGround email address and password for the brand or employee mailbox.</p>
                            <button type="button" x-on:click="settingsOpen = true" class="mt-5 inline-flex h-11 items-center justify-center rounded-xl bg-zinc-950 px-5 text-sm font-semibold text-amber-100 shadow-sm dark:bg-amber-400 dark:text-zinc-950">
                                Add Mailbox
                            </button>
                        </div>
                    </div>
                @else
                    <div class="border-b border-slate-200 px-4 py-3 dark:border-zinc-800">
                        <h2 class="font-semibold text-slate-900 dark:text-zinc-100">{{ $folder === 'Sent' ? 'Sent Mail' : 'Inbox' }}</h2>
                    </div>

                    <div class="divide-y divide-slate-100 dark:divide-zinc-800">
                        @forelse ($messages as $message)
                            <a href="{{ route('email.index', ['account' => $account->id, 'folder' => $folder, 'message' => $message->id]) }}"
                               class="{{ $selectedMessage?->id === $message->id ? 'bg-amber-50 dark:bg-amber-400/10' : 'bg-white hover:bg-slate-50 dark:bg-zinc-900 dark:hover:bg-zinc-800/70' }} block px-4 py-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-bold text-slate-900 dark:text-zinc-100">
                                            {{ $folder === 'Sent' ? collect($message->to)->implode(', ') : ($message->from_name ?: $message->from_email ?: 'Unknown sender') }}
                                        </p>
                                        <p class="mt-1 truncate text-sm font-semibold text-slate-700 dark:text-zinc-200">{{ $message->subject ?: '(No subject)' }}</p>
                                        <p class="mt-1 line-clamp-2 text-xs text-slate-500 dark:text-zinc-400">{{ $message->body_text }}</p>
                                    </div>
                                    <span class="shrink-0 text-xs text-slate-400 dark:text-zinc-500">{{ $message->sent_at?->format('M d') }}</span>
                                </div>
                            </a>
                        @empty
                            <div class="px-4 py-16 text-center text-sm text-slate-500 dark:text-zinc-400">
                                {{ $folder === 'Sent' ? 'No sent email yet.' : 'No synced inbox messages yet.' }}
                            </div>
                        @endforelse
                    </div>

                    @if ($messages instanceof \Illuminate\Contracts\Pagination\Paginator && $messages->hasPages())
                        <div class="border-t border-slate-200 px-4 py-3 dark:border-zinc-800">
                            {{ $messages->links() }}
                        </div>
                    @endif
                @endif
            </section>

            <section class="min-h-[24rem] bg-white p-6 dark:bg-zinc-900">
                @if ($selectedMessage)
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <h2 class="text-xl font-bold text-slate-900 dark:text-zinc-100">{{ $selectedMessage->subject ?: '(No subject)' }}</h2>
                            <p class="mt-2 text-sm text-slate-500 dark:text-zinc-400">
                                {{ $selectedMessage->from_name ?: $selectedMessage->from_email ?: $account?->display_name }}
                                @if ($selectedMessage->from_email)
                                    <span>({{ $selectedMessage->from_email }})</span>
                                @endif
                            </p>
                        </div>
                        <span class="text-sm text-slate-500 dark:text-zinc-400">{{ $selectedMessage->sent_at?->format('m/d/Y @ h:i A') }}</span>
                    </div>

                    <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-5 text-sm leading-7 text-slate-700 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-200">
                        @if ($selectedMessage->body_html)
                            {!! nl2br(e(strip_tags($selectedMessage->body_html))) !!}
                        @else
                            {!! nl2br(e($selectedMessage->body_text ?: 'No message body.')) !!}
                        @endif
                    </div>
                @else
                    <div class="flex h-full min-h-[24rem] items-center justify-center text-center text-sm text-slate-500 dark:text-zinc-400">
                        Select an email to read.
                    </div>
                @endif
            </section>
        </div>

        <div x-show="composeOpen"
             x-cloak
             x-transition.opacity
             class="crm-top-modal-backdrop flex items-end justify-end bg-slate-950/60 p-4 sm:items-center sm:p-6">
            <form method="POST" action="{{ route('email.send') }}" data-no-page-loader class="crm-modal-panel w-full max-w-2xl rounded-2xl bg-white shadow-2xl ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                @csrf
                <input type="hidden" name="email_account_id" value="{{ $account?->id }}">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-zinc-800">
                    <h2 class="font-bold text-slate-900 dark:text-zinc-100">New Message</h2>
                    <button type="button" x-on:click="composeOpen = false" class="rounded-lg px-3 py-1 text-sm font-semibold text-slate-500 hover:bg-slate-100 dark:text-zinc-400 dark:hover:bg-zinc-800">Close</button>
                </div>
                <div class="space-y-4 p-5">
                    <div>
                        <label for="to" class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">To</label>
                        <input id="to" name="to" value="{{ old('to') }}" class="w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100">
                        <x-input-error :messages="$errors->get('to')" class="mt-2" />
                    </div>
                    <div>
                        <label for="cc" class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">CC</label>
                        <input id="cc" name="cc" value="{{ old('cc') }}" class="w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100">
                    </div>
                    <div>
                        <label for="subject" class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Subject</label>
                        <input id="subject" name="subject" value="{{ old('subject') }}" class="w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100">
                        <x-input-error :messages="$errors->get('subject')" class="mt-2" />
                    </div>
                    <div>
                        <label for="body" class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Message</label>
                        <textarea id="body" name="body" rows="10" class="w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100">{{ old('body') }}</textarea>
                        <x-input-error :messages="$errors->get('body')" class="mt-2" />
                    </div>
                </div>
                <div class="flex justify-end gap-3 border-t border-slate-200 px-5 py-4 dark:border-zinc-800">
                    <button type="button" x-on:click="composeOpen = false" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-zinc-800 dark:text-zinc-200">Cancel</button>
                    <button type="submit" class="rounded-xl bg-zinc-950 px-5 py-2 text-sm font-semibold text-amber-100 dark:bg-amber-400 dark:text-zinc-950">Send</button>
                </div>
            </form>
        </div>

        <div x-show="settingsOpen"
             x-cloak
             x-transition.opacity
             class="crm-top-modal-backdrop flex items-start justify-center overflow-y-auto bg-slate-950/60 p-4 sm:p-6">
            <div class="crm-modal-panel w-full max-w-4xl rounded-2xl bg-white shadow-2xl ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-zinc-800">
                    <div>
                        <h2 class="font-bold text-slate-900 dark:text-zinc-100">{{ $account ? 'Mail Settings' : 'Connect Mailbox' }}</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Use the email address and password from SiteGround webmail.</p>
                    </div>
                    <button type="button" x-on:click="settingsOpen = false" class="rounded-lg px-3 py-1 text-sm font-semibold text-slate-500 hover:bg-slate-100 dark:text-zinc-400 dark:hover:bg-zinc-800">Close</button>
                </div>

                <form method="POST" action="{{ $account ? route('email.accounts.update', $account) : route('email.accounts.store') }}" class="p-5">
                    @csrf
                    @if ($account)
                        @method('PUT')
                    @endif

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="display_name" class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Display Name</label>
                            <input id="display_name" name="display_name" value="{{ old('display_name', $account?->display_name ?? auth()->user()->brand?->imprint_name ?? auth()->user()->first_name) }}" class="w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100">
                            <x-input-error :messages="$errors->get('display_name')" class="mt-2" />
                        </div>
                        <div>
                            <label for="email_address" class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Email Address</label>
                            <input id="email_address" name="email_address" type="email" value="{{ old('email_address', $account?->email_address ?? auth()->user()->email) }}" class="w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100">
                            <x-input-error :messages="$errors->get('email_address')" class="mt-2" />
                        </div>
                        <div>
                            <label for="username" class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Username</label>
                            <input id="username" name="username" value="{{ old('username', $account?->username ?? $account?->email_address ?? auth()->user()->email) }}" class="w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100">
                            <x-input-error :messages="$errors->get('username')" class="mt-2" />
                        </div>
                        <div>
                            <label for="password" class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Mailbox Password</label>
                            <input id="password" name="password" type="password" placeholder="{{ $account ? 'Leave blank to keep current password' : '' }}" class="w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100">
                            <x-input-error :messages="$errors->get('password')" class="mt-2" />
                        </div>
                    </div>

                    <div class="mt-6 grid gap-4 md:grid-cols-2">
                        <div class="rounded-xl border border-slate-200 p-4 dark:border-zinc-800">
                            <h3 class="font-semibold text-slate-900 dark:text-zinc-100">Incoming IMAP</h3>
                            <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400">Use the exact incoming server from SiteGround Mail Configuration.</p>
                            <div class="mt-4 grid gap-4 sm:grid-cols-[1fr_7rem_8rem]">
                                <input name="imap_host" value="{{ old('imap_host', $account?->imap_host ?? 'mail.siteground.net') }}" class="rounded-xl border-slate-200 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100">
                                <input name="imap_port" type="number" value="{{ old('imap_port', $account?->imap_port ?? 993) }}" class="rounded-xl border-slate-200 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100">
                                <select name="imap_encryption" class="rounded-xl border-slate-200 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100">
                                    @foreach (['ssl' => 'SSL', 'tls' => 'TLS', 'none' => 'None'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('imap_encryption', $account?->imap_encryption ?? 'ssl') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200 p-4 dark:border-zinc-800">
                            <h3 class="font-semibold text-slate-900 dark:text-zinc-100">Outgoing SMTP</h3>
                            <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400">SiteGround commonly uses SMTP port 465 with SSL.</p>
                            <div class="mt-4 grid gap-4 sm:grid-cols-[1fr_7rem_8rem]">
                                <input name="smtp_host" value="{{ old('smtp_host', $account?->smtp_host ?? 'mail.siteground.net') }}" class="rounded-xl border-slate-200 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100">
                                <input name="smtp_port" type="number" value="{{ old('smtp_port', $account?->smtp_port ?? 465) }}" class="rounded-xl border-slate-200 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100">
                                <select name="smtp_encryption" class="rounded-xl border-slate-200 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100">
                                    @foreach (['ssl' => 'SSL', 'tls' => 'TLS', 'none' => 'None'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('smtp_encryption', $account?->smtp_encryption ?? 'ssl') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    @if ($canShareAccount && ! $account)
                        <div class="mt-5 rounded-xl border border-slate-200 p-4 dark:border-zinc-800">
                            <label class="flex items-start gap-3 text-sm">
                                <input type="checkbox" name="is_shared" value="1" class="mt-1 rounded border-slate-300 text-amber-600 focus:ring-amber-500" @checked(old('is_shared'))>
                                <span>
                                    <span class="block font-semibold text-slate-900 dark:text-zinc-100">Share this mailbox with the brand</span>
                                    <span class="mt-1 block text-slate-500 dark:text-zinc-400">Use this for brand accounts like Inkspire or a future Horizon Maple Media mailbox.</span>
                                </span>
                            </label>
                            <select name="brand_id" class="mt-4 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100">
                                @foreach ($brands as $brand)
                                    <option value="{{ $brand->id }}" @selected(old('brand_id', auth()->user()->brand_id) == $brand->id)>{{ $brand->imprint_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" x-on:click="settingsOpen = false" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-zinc-800 dark:text-zinc-200">Cancel</button>
                        <button type="submit" class="rounded-xl bg-zinc-950 px-5 py-2 text-sm font-semibold text-amber-100 dark:bg-amber-400 dark:text-zinc-950">
                            {{ $account ? 'Save Settings' : 'Connect Mailbox' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
