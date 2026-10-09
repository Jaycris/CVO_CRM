<x-app-layout>
    <x-slot name="header">
        Email
    </x-slot>

    <div class="space-y-6" x-data="{ composeOpen: @js($errors->has('to') || $errors->has('subject') || $errors->has('body')), settingsOpen: @js($canManageEmailAccounts && ($errors->has('email_address') || request()->boolean('settings') || ! $account)), mailboxType: @js(old('mailbox_type', $settingsAccount?->user_id ? 'employee' : 'brand')) }">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">Email</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                    Connect SiteGround mailboxes and give employees a ready-to-use CRM inbox.
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
                @if ($canManageEmailAccounts)
                    <a href="{{ route('email.index', ['settings' => 1, 'new' => 1]) }}" class="inline-flex h-11 items-center justify-center rounded-xl bg-zinc-950 px-4 text-sm font-semibold text-amber-100 shadow-sm hover:bg-zinc-800 dark:bg-amber-400 dark:text-zinc-950">
                        Add Mailbox
                    </a>
                    <button type="button" x-on:click="settingsOpen = true" class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">
                        Admin Mail Settings
                    </button>
                @endif
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

        <div class="grid min-h-[calc(100vh-13rem)] grid-cols-1 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800 xl:grid-cols-[18rem_minmax(0,1fr)]">
            <aside class="border-b border-slate-200 bg-slate-50/80 p-4 dark:border-zinc-800 dark:bg-zinc-950/50 xl:border-b-0 xl:border-r">
                <div class="space-y-3">
                    <label for="email_account" class="text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-zinc-400">Mailbox</label>
                    <select id="email_account"
                            onchange="if (this.value) window.location.href = this.value"
                            class="w-full truncate rounded-xl border-slate-200 bg-white text-sm font-semibold text-slate-900 shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100">
                        @forelse ($accounts as $mailAccount)
                            <option value="{{ route('email.index', ['account' => $mailAccount->id, 'folder' => $folder]) }}" @selected($account?->id === $mailAccount->id)>
                                {{ $mailAccount->email_address }}
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
                            {{ $account->is_shared ? 'Brand mailbox' : 'Employee mailbox' }}
                            @if ($account->user)
                                for {{ $account->user->email }}
                            @elseif ($account->brand)
                                for {{ $account->brand->imprint_name }}
                            @endif
                        </p>
                        <p class="mt-3 text-xs text-slate-500 dark:text-zinc-400">
                            Last sync: {{ $account->last_synced_at?->format('m/d/Y @ h:i A') ?? 'Not synced yet' }}
                        </p>
                    </div>
                @endif
            </aside>

            <section class="min-h-[32rem] bg-white dark:bg-zinc-900">
                @if (! $account)
                    <div class="flex h-full min-h-[20rem] items-center justify-center p-8 text-center">
                        <div>
                            <h2 class="text-lg font-bold text-slate-900 dark:text-zinc-100">
                                {{ $canManageEmailAccounts ? 'Connect the first brand mailbox' : 'Email is not configured yet' }}
                            </h2>
                            <p class="mt-2 text-sm text-slate-500 dark:text-zinc-400">
                                {{ $canManageEmailAccounts ? 'Add a SiteGround mailbox for a brand so employees can use email immediately.' : 'Please ask an admin to connect the mailbox for your brand.' }}
                            </p>
                            @if ($canManageEmailAccounts)
                                <button type="button" x-on:click="settingsOpen = true" class="mt-5 inline-flex h-11 items-center justify-center rounded-xl bg-zinc-950 px-5 text-sm font-semibold text-amber-100 shadow-sm dark:bg-amber-400 dark:text-zinc-950">
                                    Add Mailbox
                                </button>
                            @endif
                        </div>
                    </div>
                @elseif ($selectedMessage)
                    <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-zinc-800">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('email.index', ['account' => $account->id, 'folder' => $folder]) }}"
                               class="inline-flex h-9 w-9 items-center justify-center rounded-full text-xl text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                               aria-label="Back to inbox">
                                &larr;
                            </a>
                            <form method="POST" action="{{ route('email.accounts.sync', $account) }}">
                                @csrf
                                <button type="submit" class="inline-flex h-9 items-center rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600 shadow-sm hover:bg-slate-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800">
                                    Refresh
                                </button>
                            </form>
                        </div>
                        <span class="text-xs font-medium text-slate-500 dark:text-zinc-400">
                            {{ $selectedMessage->sent_at?->format('M d, Y h:i A') }}
                        </span>
                    </div>

                    <article class="mx-auto max-w-6xl px-6 py-8">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <h2 class="text-2xl font-normal leading-tight text-slate-950 dark:text-zinc-100">{{ $selectedMessage->subject ?: '(No subject)' }}</h2>
                                <div class="mt-6 flex items-start gap-4">
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-blue-100 text-sm font-bold text-blue-700 dark:bg-blue-400/20 dark:text-blue-200">
                                        {{ strtoupper(substr($selectedMessage->from_name ?: $selectedMessage->from_email ?: $account->display_name, 0, 1)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-semibold text-slate-900 dark:text-zinc-100">
                                            {{ $selectedMessage->from_name ?: $selectedMessage->from_email ?: $account?->display_name }}
                                            @if ($selectedMessage->from_email)
                                                <span class="font-normal text-slate-500 dark:text-zinc-400">&lt;{{ $selectedMessage->from_email }}&gt;</span>
                                            @endif
                                        </p>
                                        <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                                            {{ $folder === 'Sent' ? 'sent from ' . $account->email_address : 'to ' . $account->email_address }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-8 rounded-xl border border-slate-200 bg-white p-6 text-base leading-8 text-slate-800 shadow-sm dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-200">
                            @if ($selectedMessage->body_html)
                                {!! nl2br(e(strip_tags($selectedMessage->body_html))) !!}
                            @else
                                {!! nl2br(e($selectedMessage->body_text ?: 'No message body.')) !!}
                            @endif
                        </div>
                    </article>
                @else
                    <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-zinc-800">
                        <div>
                            <h2 class="font-semibold text-slate-900 dark:text-zinc-100">{{ $folder === 'Sent' ? 'Sent Mail' : 'Inbox' }}</h2>
                            <p class="text-xs text-slate-500 dark:text-zinc-400">{{ $messages instanceof \Illuminate\Contracts\Pagination\Paginator ? $messages->total() : 0 }} messages</p>
                        </div>
                        @if ($account)
                            <form method="POST" action="{{ route('email.accounts.sync', $account) }}">
                                @csrf
                                <button type="submit" class="inline-flex h-9 items-center rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600 shadow-sm hover:bg-slate-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800">
                                    Refresh
                                </button>
                            </form>
                        @endif
                    </div>

                    <div class="divide-y divide-slate-100 dark:divide-zinc-800">
                        @forelse ($messages as $message)
                            <a href="{{ route('email.index', ['account' => $account->id, 'folder' => $folder, 'message' => $message->id]) }}"
                               class="{{ $selectedMessage?->id === $message->id ? 'bg-blue-50 dark:bg-blue-400/10' : 'bg-white hover:bg-slate-50 dark:bg-zinc-900 dark:hover:bg-zinc-800/70' }} block px-3 py-2.5">
                                <div class="grid items-center gap-3 text-sm md:grid-cols-[1rem_1.25rem_minmax(8rem,12rem)_minmax(0,1fr)_4.5rem]">
                                    <span class="hidden h-4 w-4 rounded border border-slate-300 bg-white dark:border-zinc-700 dark:bg-zinc-950 md:block"></span>
                                    <span class="hidden text-center text-lg leading-none text-slate-300 dark:text-zinc-600 md:block">&#9734;</span>
                                    <p class="truncate font-semibold text-slate-900 dark:text-zinc-100">
                                        {{ $folder === 'Sent' ? collect($message->to)->implode(', ') : ($message->from_name ?: $message->from_email ?: 'Unknown sender') }}
                                    </p>
                                    <div class="min-w-0">
                                        <p class="truncate text-slate-900 dark:text-zinc-100">
                                            <span class="font-semibold">{{ $message->subject ?: '(No subject)' }}</span>
                                            @if ($message->body_text)
                                                <span class="text-slate-500 dark:text-zinc-400"> - {{ $message->body_text }}</span>
                                            @endif
                                        </p>
                                    </div>
                                    <span class="text-right text-xs font-medium text-slate-500 dark:text-zinc-400">{{ $message->sent_at?->format('M d') }}</span>
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
        </div>

        <template x-teleport="body">
            <div x-show="composeOpen"
                 x-cloak
                 x-transition.opacity
                 class="crm-top-modal-backdrop flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm sm:p-6">
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
        </template>

        @if ($canManageEmailAccounts)
            <template x-teleport="body">
            <div x-show="settingsOpen"
                 x-cloak
                 x-transition.opacity
                 class="crm-top-modal-backdrop flex items-start justify-center overflow-y-auto bg-slate-950/70 p-4 backdrop-blur-sm sm:p-6">
                <div class="crm-modal-panel my-auto w-full max-w-6xl rounded-2xl bg-white shadow-2xl ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-zinc-800">
                        <div>
                            <h2 class="font-bold text-slate-900 dark:text-zinc-100">{{ $settingsAccount ? 'Admin Mail Settings' : 'Connect Mailbox' }}</h2>
                            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Configure a brand mailbox for a team or an employee mailbox for one CRM user.</p>
                        </div>
                        <button type="button" x-on:click="settingsOpen = false" class="rounded-lg px-3 py-1 text-sm font-semibold text-slate-500 hover:bg-slate-100 dark:text-zinc-400 dark:hover:bg-zinc-800">Close</button>
                    </div>

                    <form method="POST" action="{{ $settingsAccount ? route('email.accounts.update', $settingsAccount) : route('email.accounts.store') }}" class="p-5">
                        @csrf
                        @if ($settingsAccount)
                            @method('PUT')
                        @endif

                        @if (! $settingsAccount)
                            <div class="mb-5">
                                <label for="mailbox_type" class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Mailbox Access</label>
                                <select id="mailbox_type" name="mailbox_type" x-model="mailboxType" class="w-full rounded-xl border-slate-200 text-sm font-semibold shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100">
                                    <option value="brand">Brand mailbox - everyone assigned to the brand can use it</option>
                                    <option value="employee">Employee mailbox - only the selected employee can use it</option>
                                </select>
                                <x-input-error :messages="$errors->get('mailbox_type')" class="mt-2" />
                            </div>
                        @else
                            <input type="hidden" name="mailbox_type" value="{{ $settingsAccount->user_id ? 'employee' : 'brand' }}">
                        @endif

                        <div class="mb-5" x-show="mailboxType === 'brand'">
                            <label for="brand_id" class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Brand</label>
                            @if ($settingsAccount)
                                <input type="hidden" name="brand_id" value="{{ $settingsAccount->brand_id }}">
                                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-900 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100">
                                    {{ $settingsAccount->brand?->imprint_name ?? 'Brand mailbox' }}
                                </div>
                            @else
                                <select id="brand_id" name="brand_id" class="w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100">
                                    @foreach ($brands as $brand)
                                        <option value="{{ $brand->id }}" @selected(old('brand_id', auth()->user()->brand_id) == $brand->id)>{{ $brand->imprint_name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('brand_id')" class="mt-2" />
                            @endif
                        </div>

                        <div class="mb-5" x-show="mailboxType === 'employee'">
                            <label for="user_id" class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Employee</label>
                            @if ($settingsAccount)
                                <input type="hidden" name="user_id" value="{{ $settingsAccount->user_id }}">
                                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-900 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100">
                                    {{ $settingsAccount->user ? trim(($settingsAccount->user->first_name ?? '') . ' ' . ($settingsAccount->user->last_name ?? '')) : 'Employee mailbox' }}
                                </div>
                            @else
                                <select id="user_id" name="user_id" class="w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100">
                                    @foreach ($users as $mailUser)
                                        @php($mailUserName = trim(($mailUser->first_name ?? '') . ' ' . ($mailUser->last_name ?? '')) ?: $mailUser->email)
                                        <option value="{{ $mailUser->id }}" @selected(old('user_id') == $mailUser->id)>{{ $mailUserName }} - {{ $mailUser->email }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('user_id')" class="mt-2" />
                            @endif
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="display_name" class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Display Name</label>
                            <input id="display_name" name="display_name" value="{{ old('display_name', $settingsAccount?->display_name ?? auth()->user()->brand?->imprint_name ?? auth()->user()->first_name) }}" class="w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100">
                            <x-input-error :messages="$errors->get('display_name')" class="mt-2" />
                        </div>
                        <div>
                            <label for="email_address" class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Email Address</label>
                            <input id="email_address" name="email_address" type="email" value="{{ old('email_address', $settingsAccount?->email_address ?? auth()->user()->email) }}" class="w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100">
                            <x-input-error :messages="$errors->get('email_address')" class="mt-2" />
                        </div>
                        <div>
                            <label for="username" class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Username</label>
                            <input id="username" name="username" value="{{ old('username', $settingsAccount?->username ?? $settingsAccount?->email_address ?? auth()->user()->email) }}" class="w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100">
                            <x-input-error :messages="$errors->get('username')" class="mt-2" />
                        </div>
                        <div>
                            <label for="password" class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Mailbox Password</label>
                            <input id="password" name="password" type="password" placeholder="{{ $settingsAccount ? 'Leave blank to keep current password' : '' }}" class="w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100">
                            <x-input-error :messages="$errors->get('password')" class="mt-2" />
                        </div>
                        </div>

                        <div class="mt-6 grid gap-4 md:grid-cols-2">
                        <div class="rounded-xl border border-slate-200 p-4 dark:border-zinc-800">
                            <h3 class="font-semibold text-slate-900 dark:text-zinc-100">Incoming IMAP</h3>
                            <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400">Use the exact incoming server from SiteGround Mail Configuration.</p>
                            <div class="mt-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_5.5rem_6.5rem]">
                                <input name="imap_host" value="{{ old('imap_host', $settingsAccount?->imap_host ?? 'mail.siteground.net') }}" class="min-w-0 rounded-xl border-slate-200 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100">
                                <input name="imap_port" type="number" value="{{ old('imap_port', $settingsAccount?->imap_port ?? 993) }}" class="min-w-0 rounded-xl border-slate-200 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100">
                                <select name="imap_encryption" class="min-w-0 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100">
                                    @foreach (['ssl' => 'SSL', 'tls' => 'TLS', 'none' => 'None'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('imap_encryption', $settingsAccount?->imap_encryption ?? 'ssl') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200 p-4 dark:border-zinc-800">
                            <h3 class="font-semibold text-slate-900 dark:text-zinc-100">Outgoing SMTP</h3>
                            <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400">SiteGround commonly uses SMTP port 465 with SSL.</p>
                            <div class="mt-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_5.5rem_6.5rem]">
                                <input name="smtp_host" value="{{ old('smtp_host', $settingsAccount?->smtp_host ?? 'mail.siteground.net') }}" class="min-w-0 rounded-xl border-slate-200 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100">
                                <input name="smtp_port" type="number" value="{{ old('smtp_port', $settingsAccount?->smtp_port ?? 465) }}" class="min-w-0 rounded-xl border-slate-200 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100">
                                <select name="smtp_encryption" class="min-w-0 w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100">
                                    @foreach (['ssl' => 'SSL', 'tls' => 'TLS', 'none' => 'None'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('smtp_encryption', $settingsAccount?->smtp_encryption ?? 'ssl') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        </div>

                        <div class="mt-6 flex justify-end gap-3">
                            <button type="button" x-on:click="settingsOpen = false" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-zinc-800 dark:text-zinc-200">Cancel</button>
                            <button type="submit" class="rounded-xl bg-zinc-950 px-5 py-2 text-sm font-semibold text-amber-100 dark:bg-amber-400 dark:text-zinc-950">
                                {{ $settingsAccount ? 'Save Settings' : 'Connect Mailbox' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            </template>
        @endif
    </div>
</x-app-layout>
