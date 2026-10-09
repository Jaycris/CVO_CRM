<x-app-layout>
    <x-slot name="header">
        Email
    </x-slot>

    @php
        $emailMessageRecords = $messages instanceof \Illuminate\Contracts\Pagination\Paginator
            ? $messages->getCollection()
            : collect();
        $emailMessagePayload = $account
            ? $emailMessageRecords->mapWithKeys(function ($message) use ($account, $folder) {
                $bodyText = $message->body_text ?: 'No message body.';
                $textLooksLikeHtml = is_string($bodyText) && preg_match('/^\s*(<!doctype|<html|<body|<table|<div|<p)\b/i', $bodyText);
                $textLooksLikeSource = is_string($bodyText) && preg_match('/^\s*(#outlook\b|@media\b|body\s*\{|table\s*,\s*td\s*\{|img\s*\{|p\s*\{|\.moz-text-html\b|\.mj-[a-z0-9_-]+\b)/i', $bodyText);

                return [
                    $message->id => [
                        'id' => $message->id,
                        'folder' => $folder,
                        'url' => route('email.index', ['account' => $account->id, 'folder' => $folder, 'message' => $message->id]),
                        'listUrl' => route('email.index', ['account' => $account->id, 'folder' => $folder]),
                        'readUrl' => route('email.messages.read', ['account' => $account, 'message' => $message]),
                        'subject' => $message->subject ?: '(No subject)',
                        'fromName' => $message->from_name ?: $message->from_email ?: $account->display_name,
                        'fromEmail' => $message->from_email,
                        'recipientLine' => $folder === 'Sent' ? 'sent from ' . $account->email_address : 'to ' . $account->email_address,
                        'initial' => strtoupper(substr($message->from_name ?: $message->from_email ?: $account->display_name, 0, 1)),
                        'sentAt' => $message->sent_at?->format('M d, Y h:i A'),
                        'bodyText' => $bodyText,
                        'bodyHtml' => $message->body_html,
                        'textLooksLikeHtml' => (bool) $textLooksLikeHtml,
                        'textLooksLikeSource' => (bool) $textLooksLikeSource,
                        'isSeen' => (bool) $message->is_seen,
                    ],
                ];
            })
            : collect();
    @endphp

    <div class="space-y-6" x-data="{
        composeOpen: @js($errors->has('to') || $errors->has('cc') || $errors->has('subject') || $errors->has('body')),
        settingsOpen: @js($canManageEmailAccounts && ($errors->has('email_address') || request()->boolean('settings') || ! $account)),
        signatureSettingsOpen: @js($errors->has('email_signature_html')),
        mailboxType: 'employee',
        bodyText: @js(old('body', '')),
        includeSignature: @js((bool) $emailSignature),
        signatureText: @js($emailSignature['text'] ?? ''),
        signatureHtml: @js(old('email_signature_html', $signatureEditorHtml)),
        selectedSignatureImage: null,
        selectedSignatureImageWidth: 240,
        signatureImageSelected: false,
        attachmentNames: [],
        imageNames: [],
        selectedMessages: [],
        activeEmailMessage: null,
        emailMessages: @js($emailMessagePayload),
        currentMailboxUrl: @js($account ? route('email.index', ['account' => $account->id, 'folder' => $folder]) : url()->current()),
        autoRefreshUrl: @js($account ? route('email.accounts.sync', $account) : null),
        emailFolder: @js($folder),
        autoRefreshTimer: null,
        autoRefreshing: false,
        sendingEmail: false,
        showMoreOptions: false,
        showSendOptions: false,
        showFormatting: false,
        showEmojiPicker: false,
        showCc: @js(filled(old('cc')) || $errors->has('cc')),
        scheduleNote: '',
        composeNotice: '',
        emojis: ['😀','😃','😁','😊','😂','🤣','😉','😍','🥳','👍','🙏','🔥','⭐','✅','📌','📎','📅','💡','🎉','❤️'],
        insertText(text) {
            const body = this.$refs.body;
            const start = body?.selectionStart ?? this.bodyText.length;
            const end = body?.selectionEnd ?? this.bodyText.length;
            this.bodyText = this.bodyText.slice(0, start) + text + this.bodyText.slice(end);
            this.$nextTick(() => {
                if (body) {
                    body.focus();
                    body.selectionStart = body.selectionEnd = start + text.length;
                }
            });
        },
        wrapSelection(before, after = before) {
            const body = this.$refs.body;
            const start = body?.selectionStart ?? this.bodyText.length;
            const end = body?.selectionEnd ?? this.bodyText.length;
            const selected = this.bodyText.slice(start, end);
            const text = selected || 'text';
            this.bodyText = this.bodyText.slice(0, start) + before + text + after + this.bodyText.slice(end);
            this.$nextTick(() => {
                if (body) {
                    body.focus();
                    body.selectionStart = start + before.length;
                    body.selectionEnd = start + before.length + text.length;
                }
            });
        },
        applyFormat(format) {
            if (format === 'bold') this.wrapSelection('**');
            if (format === 'italic') this.wrapSelection('*');
            if (format === 'underline') this.wrapSelection('__');
            if (format === 'quote') this.insertText('\n> ');
            if (format === 'bullets') this.insertText('\n- ');
            if (format === 'numbers') this.insertText('\n1. ');
            if (format === 'indent') this.insertText('    ');
            if (format === 'remove') this.composeNotice = 'Formatting cleared for new text.';
        },
        handleComposeAction(action) {
            const wasFormatting = this.showFormatting;
            const wasEmojiPicker = this.showEmojiPicker;
            const wasMoreOptions = this.showMoreOptions;
            this.showMoreOptions = false;
            this.showSendOptions = false;
            this.showEmojiPicker = false;
            this.scheduleNote = '';
            this.composeNotice = '';
            if (action === 'format') this.showFormatting = ! wasFormatting;
            if (action === 'attach') this.$refs.attachments.click();
            if (action === 'link') {
                const url = prompt('Paste the link URL');
                if (url) this.insertText(url);
            }
            if (action === 'emoji') this.showEmojiPicker = ! wasEmojiPicker;
            if (action === 'image') this.$refs.images.click();
            if (action === 'confidential') this.insertText('\n\nConfidential: Please do not forward this message without permission.');
            if (action === 'signature') {
                if (this.signatureText) {
                    this.includeSignature = true;
                    this.signatureSettingsOpen = true;
                } else {
                    this.signatureSettingsOpen = true;
                }
            }
            if (action === 'calendar') this.insertText('\n\nMeeting invite:\nDate:\nTime:\nAgenda:\n');
            if (action === 'more') this.showMoreOptions = ! wasMoreOptions;
        },
        toggleSendOptions() {
            this.showSendOptions = ! this.showSendOptions;
            this.showMoreOptions = false;
            this.showEmojiPicker = false;
            this.scheduleNote = '';
            this.composeNotice = '';
        },
        chooseScheduleSend() {
            this.scheduleNote = 'Schedule send is ready for UI only. Backend scheduling can be connected next.';
            this.showSendOptions = false;
        },
        pickEmoji(emoji) {
            this.insertText(emoji);
            this.showEmojiPicker = false;
        },
        initEmailAutoRefresh() {
            if (! this.autoRefreshUrl || this.emailFolder !== 'INBOX') return;

            this.autoRefreshTimer = window.setInterval(() => this.autoRefreshInbox(), 3000);
            window.addEventListener('beforeunload', () => {
                if (this.autoRefreshTimer) window.clearInterval(this.autoRefreshTimer);
            });
        },
        autoRefreshInbox() {
            if (this.autoRefreshing || document.hidden || this.activeEmailMessage || this.composeOpen || this.settingsOpen || this.signatureSettingsOpen || this.selectedMessages.length > 0) {
                return;
            }

            this.autoRefreshing = true;
            fetch(this.autoRefreshUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                },
            })
                .then(response => response.ok ? response.json() : null)
                .then(data => {
                    if (data?.synced > 0) {
                        window.location.href = this.currentMailboxUrl;
                    }
                })
                .catch(() => {})
                .finally(() => {
                    this.autoRefreshing = false;
                });
        },
        openEmailMessageById(messageId) {
            const message = this.emailMessages?.[messageId];
            if (! message) return;
            this.openEmailMessage(message);
        },
        openEmailMessage(message) {
            this.activeEmailMessage = message;
            this.selectedMessages = [];
            window.history.pushState({}, '', message.url);

            if (message.folder === 'INBOX' && !message.isSeen) {
                message.isSeen = true;
                window.setTimeout(() => {
                    fetch(message.readUrl, {
                        method: 'PATCH',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                        },
                    }).catch(() => {});
                }, 750);
            }
        },
        closeEmailMessage() {
            this.activeEmailMessage = null;
            window.history.pushState({}, '', this.currentMailboxUrl);
        },
        formatSignature(command, value = null) {
            this.$refs.signatureEditor?.focus();
            document.execCommand(command, false, value);
            this.signatureHtml = this.$refs.signatureEditor?.innerHTML || '';
        },
        insertSignatureImage() {
            const url = prompt('Paste the image URL');
            if (! url) return;
            this.$refs.signatureEditor?.focus();
            document.execCommand('insertHTML', false, `<img src=&quot;${url.replace(/&quot;/g, '')}&quot; style=&quot;display:block; margin-top:16px; width:240px; max-width:100%; height:auto;&quot; alt=&quot;Signature image&quot;>`);
            this.syncSignatureEditor();
        },
        selectSignatureImage(event) {
            if (event.target?.tagName !== 'IMG') {
                this.clearSignatureImageSelection();
                return;
            }

            this.selectedSignatureImage?.classList.remove('ring-2', 'ring-blue-500');
            this.selectedSignatureImage = event.target;
            this.selectedSignatureImage.classList.add('ring-2', 'ring-blue-500');
            this.signatureImageSelected = true;
            this.selectedSignatureImageWidth = parseInt(this.selectedSignatureImage.style.width || this.selectedSignatureImage.width || 240, 10);
        },
        clearSignatureImageSelection() {
            this.selectedSignatureImage?.classList.remove('ring-2', 'ring-blue-500');
            this.selectedSignatureImage = null;
            this.signatureImageSelected = false;
        },
        resizeSignatureImage(width) {
            if (! this.selectedSignatureImage) return;
            this.selectedSignatureImageWidth = parseInt(width, 10);
            this.selectedSignatureImage.style.width = `${this.selectedSignatureImageWidth}px`;
            this.selectedSignatureImage.style.maxWidth = '100%';
            this.selectedSignatureImage.style.height = 'auto';
            this.syncSignatureEditor();
        },
        syncSignatureEditor() {
            const editor = this.$refs.signatureEditor;
            if (! editor) {
                this.signatureHtml = '';
                return;
            }

            const clone = editor.cloneNode(true);
            clone.querySelectorAll('img').forEach((image) => image.classList.remove('ring-2', 'ring-blue-500'));
            this.signatureHtml = clone.innerHTML || '';
        },
        updateFiles(type, event) {
            const names = Array.from(event.target.files || []).map(file => file.name);
            if (type === 'attachments') this.attachmentNames = names;
            if (type === 'images') this.imageNames = names;
        },
        discardCompose() {
            this.bodyText = '';
            this.attachmentNames = [];
            this.imageNames = [];
            if (this.$refs.attachments) this.$refs.attachments.value = '';
            if (this.$refs.images) this.$refs.images.value = '';
            this.showFormatting = false;
            this.showEmojiPicker = false;
            this.showMoreOptions = false;
            this.showSendOptions = false;
            this.showCc = false;
            this.includeSignature = @js((bool) $emailSignature);
            this.sendingEmail = false;
            this.scheduleNote = '';
            this.composeNotice = '';
            this.composeOpen = false;
        }
    }" x-init="initEmailAutoRefresh()">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">Email</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                    Admin assign you an email to your CRM
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if ($account)
                    <form method="POST" action="{{ route('email.accounts.sync', $account) }}">
                        @csrf
                        <button type="submit" class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">
                            Refresh
                        </button>
                    </form>
                    <button type="button" x-on:click="composeOpen = true" class="inline-flex h-11 items-center justify-center rounded-xl bg-zinc-950 px-5 text-sm font-semibold text-amber-100 shadow-sm hover:bg-zinc-800 dark:bg-amber-400 dark:text-zinc-950">
                        Compose
                    </button>
                @endif
                <button type="button" x-on:click="signatureSettingsOpen = true" class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">
                    Signature
                </button>
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

        <div class="grid min-h-[calc(100vh-13rem)] grid-cols-1 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800 xl:grid-cols-[18rem_minmax(0,1fr)]">
            <aside class="border-b border-slate-200 bg-slate-50/80 p-4 dark:border-zinc-800 dark:bg-zinc-950/50 xl:border-b-0 xl:border-r">
                <div class="space-y-3">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-zinc-400">Mailbox</p>
                    <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100">
                        {{ $account?->email_address ?? 'No mailbox connected' }}
                    </div>
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
                        <p class="text-lg font-bold leading-tight text-slate-950 dark:text-zinc-100">{{ $account->display_name ?: 'Not set' }}</p>
                        <p class="mt-1 break-words text-sm font-semibold text-slate-700 dark:text-zinc-300">{{ $account->email_address }}</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400">
                            Employee mailbox
                            @if ($account->user)
                                for {{ trim(($account->user->first_name ?? '') . ' ' . ($account->user->last_name ?? '')) ?: $account->user->email }}
                            @elseif ($account->brand)
                                waiting for employee assignment
                            @endif
                        </p>
                        <p class="mt-3 text-xs text-slate-500 dark:text-zinc-400">
                            Last refresh: {{ $account->last_synced_at?->format('m/d/Y @ h:i A') ?? 'Not refreshed yet' }}
                        </p>
                    </div>
                @endif
            </aside>

            <section class="min-h-[32rem] bg-white dark:bg-zinc-900">
                @if (! $account)
                    <div class="flex h-full min-h-[20rem] items-center justify-center p-8 text-center">
                        <div>
                            <h2 class="text-lg font-bold text-slate-900 dark:text-zinc-100">
                                {{ $canManageEmailAccounts ? 'Connect the first employee mailbox' : 'Email is not configured yet' }}
                            </h2>
                            <p class="mt-2 text-sm text-slate-500 dark:text-zinc-400">
                                {{ $canManageEmailAccounts ? 'Add a SiteGround mailbox and assign it to one CRM employee.' : 'Please ask an admin to assign your mailbox.' }}
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

                        @php
                            $messageHtml = $selectedMessage->body_html;
                            $messageText = $selectedMessage->body_text ?: 'No message body.';
                            $textLooksLikeHtml = is_string($messageText) && preg_match('/^\s*(<!doctype|<html|<body|<table|<div|<p)\b/i', $messageText);
                            $textLooksLikeSource = is_string($messageText) && preg_match('/^\s*(#outlook\b|@media\b|body\s*\{|table\s*,\s*td\s*\{|img\s*\{|p\s*\{|\.moz-text-html\b|\.mj-[a-z0-9_-]+\b)/i', $messageText);
                        @endphp
                        <div class="mt-8 rounded-xl border border-slate-200 bg-white p-6 text-base leading-8 text-slate-800 shadow-sm dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-200">
                            @if ($messageHtml || $textLooksLikeHtml)
                                <iframe title="Email message body"
                                        sandbox
                                        srcdoc="{{ $messageHtml ?: $messageText }}"
                                        class="h-[44rem] w-full rounded-lg border-0 bg-white"></iframe>
                            @elseif ($textLooksLikeSource)
                                <div class="rounded-xl bg-slate-50 px-5 py-4 text-sm leading-6 text-slate-600 dark:bg-zinc-900 dark:text-zinc-300">
                                    This email uses HTML formatting that could not be displayed cleanly. Click Refresh to try loading the formatted version again.
                                </div>
                            @else
                                {!! nl2br(e($messageText)) !!}
                            @endif
                        </div>
                    </article>
                @else
                    <div x-show="activeEmailMessage" x-cloak>
                        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-zinc-800">
                            <div class="flex items-center gap-2">
                                <button type="button"
                                        x-on:click="closeEmailMessage()"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-full text-xl text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                                        aria-label="Back to inbox">
                                    &larr;
                                </button>
                                @if ($account)
                                    <form method="POST" action="{{ route('email.accounts.sync', $account) }}">
                                        @csrf
                                        <button type="submit" class="inline-flex h-9 items-center rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600 shadow-sm hover:bg-slate-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800">
                                            Refresh
                                        </button>
                                    </form>
                                @endif
                            </div>
                            <span class="text-xs font-medium text-slate-500 dark:text-zinc-400" x-text="activeEmailMessage?.sentAt"></span>
                        </div>

                        <article class="mx-auto max-w-6xl px-6 py-8">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <h2 class="text-2xl font-normal leading-tight text-slate-950 dark:text-zinc-100" x-text="activeEmailMessage?.subject || '(No subject)'"></h2>
                                    <div class="mt-6 flex items-start gap-4">
                                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-blue-100 text-sm font-bold text-blue-700 dark:bg-blue-400/20 dark:text-blue-200" x-text="activeEmailMessage?.initial || '?'"></div>
                                        <div class="min-w-0">
                                            <p class="font-semibold text-slate-900 dark:text-zinc-100">
                                                <span x-text="activeEmailMessage?.fromName || 'Unknown sender'"></span>
                                                <span x-show="activeEmailMessage?.fromEmail" class="font-normal text-slate-500 dark:text-zinc-400">&lt;<span x-text="activeEmailMessage?.fromEmail"></span>&gt;</span>
                                            </p>
                                            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400" x-text="activeEmailMessage?.recipientLine"></p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-8 rounded-xl border border-slate-200 bg-white p-6 text-base leading-8 text-slate-800 shadow-sm dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-200">
                                <template x-if="activeEmailMessage && (activeEmailMessage.bodyHtml || activeEmailMessage.textLooksLikeHtml)">
                                    <iframe title="Email message body"
                                            sandbox
                                            x-bind:srcdoc="activeEmailMessage.bodyHtml || activeEmailMessage.bodyText"
                                            class="h-[44rem] w-full rounded-lg border-0 bg-white"></iframe>
                                </template>
                                <template x-if="activeEmailMessage && !activeEmailMessage.bodyHtml && !activeEmailMessage.textLooksLikeHtml && activeEmailMessage.textLooksLikeSource">
                                    <div class="rounded-xl bg-slate-50 px-5 py-4 text-sm leading-6 text-slate-600 dark:bg-zinc-900 dark:text-zinc-300">
                                        This email uses HTML formatting that could not be displayed cleanly. Click Refresh to try loading the formatted version again.
                                    </div>
                                </template>
                                <template x-if="activeEmailMessage && !activeEmailMessage.bodyHtml && !activeEmailMessage.textLooksLikeHtml && !activeEmailMessage.textLooksLikeSource">
                                    <div class="whitespace-pre-line" x-text="activeEmailMessage.bodyText || 'No message body.'"></div>
                                </template>
                            </div>
                        </article>
                    </div>

                    <div x-show="!activeEmailMessage">
                    <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-zinc-800">
                        <div>
                            <h2 class="font-semibold text-slate-900 dark:text-zinc-100">{{ $folder === 'Sent' ? 'Sent Mail' : 'Inbox' }}</h2>
                            <p class="text-xs text-slate-500 dark:text-zinc-400">{{ $messages instanceof \Illuminate\Contracts\Pagination\Paginator ? $messages->total() : 0 }} messages</p>
                        </div>
                        @if ($account)
                            <form id="bulk-delete-messages" method="POST" action="{{ route('email.messages.destroy', $account) }}" x-on:submit="if (selectedMessages.length === 0 || !confirm('Delete selected email messages?')) { $event.preventDefault(); }">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="folder" value="{{ $folder }}">
                            </form>
                            <div class="flex items-center gap-2">
                                <button type="submit"
                                        form="bulk-delete-messages"
                                        x-bind:disabled="selectedMessages.length === 0"
                                        x-bind:class="selectedMessages.length === 0 ? 'cursor-not-allowed opacity-40' : 'hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-400/10 dark:hover:text-rose-300'"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300"
                                        title="Delete selected"
                                        aria-label="Delete selected messages">
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path d="M7 3.5h6l.6 1.5H17v1.4H3V5h3.4L7 3.5Zm-1.8 4h9.6l-.6 9H5.8l-.6-9Zm2.1 1.4.4 6.2h4.6l.4-6.2H7.3Z"/>
                                    </svg>
                                </button>
                            <form method="POST" action="{{ route('email.accounts.sync', $account) }}">
                                @csrf
                                <button type="submit" class="inline-flex h-9 items-center rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600 shadow-sm hover:bg-slate-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800">
                                    Refresh
                                </button>
                            </form>
                            </div>
                        @endif
                    </div>

                    <div class="divide-y divide-slate-100 dark:divide-zinc-800">
                        @forelse ($messages as $message)
                            @php
                                $isUnread = $folder === 'INBOX' && ! $message->is_seen;
                                $rowClass = $isUnread
                                    ? 'bg-white hover:bg-amber-50/70 dark:bg-zinc-900 dark:hover:bg-amber-400/10'
                                    : 'bg-slate-50/70 text-slate-600 hover:bg-white dark:bg-zinc-950/60 dark:text-zinc-400 dark:hover:bg-zinc-900';
                            @endphp
                            <div class="{{ $selectedMessage?->id === $message->id ? 'bg-blue-50 dark:bg-blue-400/10' : $rowClass }} grid items-center gap-3 px-3 py-2.5 text-sm md:grid-cols-[1rem_1.25rem_minmax(8rem,12rem)_minmax(0,1fr)_4.5rem]">
                                <input type="checkbox"
                                       name="message_ids[]"
                                       value="{{ $message->id }}"
                                       form="bulk-delete-messages"
                                       x-model="selectedMessages"
                                       class="hidden h-4 w-4 rounded border-slate-300 text-rose-600 shadow-sm focus:ring-rose-500 dark:border-zinc-700 dark:bg-zinc-950 md:block">
                                <span class="{{ $isUnread ? 'text-amber-400 dark:text-amber-300' : 'text-slate-300 dark:text-zinc-600' }} hidden text-center text-lg leading-none md:block">&#9734;</span>
                                <a href="{{ route('email.index', ['account' => $account->id, 'folder' => $folder, 'message' => $message->id]) }}"
                                   x-on:click.prevent="openEmailMessageById({{ $message->id }})"
                                   data-no-page-loader
                                   class="contents">
                                    <p class="{{ $isUnread ? 'font-bold text-slate-950 dark:text-zinc-50' : 'font-medium text-slate-600 dark:text-zinc-400' }} truncate">
                                        {{ $folder === 'Sent' ? collect($message->to)->implode(', ') : ($message->from_name ?: $message->from_email ?: 'Unknown sender') }}
                                    </p>
                                    <div class="min-w-0">
                                        <p class="{{ $isUnread ? 'text-slate-950 dark:text-zinc-50' : 'text-slate-600 dark:text-zinc-400' }} truncate">
                                            <span class="{{ $isUnread ? 'font-bold' : 'font-medium' }}">{{ $message->subject ?: '(No subject)' }}</span>
                                            @php
                                                $previewText = $message->body_text;
                                                $previewLooksLikeSource = is_string($previewText) && preg_match('/^\s*(<!doctype|<html|<body|<table|#outlook\b|@media\b|body\s*\{|table\s*,\s*td\s*\{|\.moz-text-html\b|\.mj-[a-z0-9_-]+\b)/i', $previewText);
                                            @endphp
                                            @if ($previewText && ! $previewLooksLikeSource)
                                                <span class="{{ $isUnread ? 'text-slate-600 dark:text-zinc-300' : 'text-slate-400 dark:text-zinc-500' }}"> - {{ $previewText }}</span>
                                            @elseif ($message->body_html || $previewLooksLikeSource)
                                                <span class="{{ $isUnread ? 'text-slate-600 dark:text-zinc-300' : 'text-slate-400 dark:text-zinc-500' }}"> - HTML email</span>
                                            @endif
                                        </p>
                                    </div>
                                    <span class="{{ $isUnread ? 'font-bold text-slate-700 dark:text-zinc-200' : 'font-medium text-slate-400 dark:text-zinc-500' }} text-right text-xs">{{ $message->sent_at?->format('M d') }}</span>
                                </a>
                            </div>
                        @empty
                            <div class="px-4 py-16 text-center text-sm text-slate-500 dark:text-zinc-400">
                                {{ $folder === 'Sent' ? 'No sent email yet.' : 'No refreshed inbox messages yet.' }}
                            </div>
                        @endforelse
                    </div>

                    @if ($messages instanceof \Illuminate\Contracts\Pagination\Paginator && $messages->hasPages())
                        <div class="border-t border-slate-200 px-4 py-3 dark:border-zinc-800">
                            {{ $messages->links() }}
                        </div>
                    @endif
                    </div>
                @endif
            </section>
        </div>

        <template x-teleport="body">
            <div x-show="composeOpen"
                 x-cloak
                 x-transition.opacity
                 class="crm-top-modal-backdrop flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm sm:p-6">
                <form method="POST" action="{{ route('email.send') }}" enctype="multipart/form-data" data-no-page-loader x-on:submit="sendingEmail = true" class="crm-modal-panel max-h-[calc(100vh-2rem)] w-full max-w-5xl overflow-y-auto rounded-2xl bg-white shadow-2xl ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                    @csrf
                    <input type="hidden" name="email_account_id" value="{{ $account?->id }}">
                    <input type="hidden" name="include_signature" x-bind:value="includeSignature ? 1 : 0">
                    <input x-ref="attachments" type="file" name="attachments[]" multiple class="hidden" x-on:change="updateFiles('attachments', $event)">
                    <input x-ref="images" type="file" name="inline_images[]" accept="image/*" multiple class="hidden" x-on:change="updateFiles('images', $event)">
                    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-zinc-800">
                        <h2 class="font-bold text-slate-900 dark:text-zinc-100">New Message</h2>
                        <button type="button" x-on:click="composeOpen = false" class="rounded-lg px-3 py-1 text-sm font-semibold text-slate-500 hover:bg-slate-100 dark:text-zinc-400 dark:hover:bg-zinc-800">Close</button>
                    </div>
                    <div class="space-y-4 p-5">
                        <div>
                            <div class="mb-1 flex items-center justify-between gap-3">
                                <label for="to" class="block text-xs font-bold uppercase tracking-wide text-slate-500">To</label>
                                <button type="button"
                                        x-show="! showCc"
                                        x-on:click="showCc = true; $nextTick(() => $refs.cc?.focus())"
                                        class="rounded-md px-2 py-1 text-xs font-bold uppercase tracking-wide text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100">
                                    CC
                                </button>
                            </div>
                            <input id="to" name="to" value="{{ old('to') }}" class="w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100">
                            <x-input-error :messages="$errors->get('to')" class="mt-2" />
                        </div>
                        <div x-show="showCc" x-cloak>
                            <div class="mb-1 flex items-center justify-between gap-3">
                                <label for="cc" class="block text-xs font-bold uppercase tracking-wide text-slate-500">CC</label>
                                <button type="button"
                                        x-on:click="showCc = false; $refs.cc.value = ''"
                                        class="rounded-md px-2 py-1 text-xs font-semibold text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100">
                                    Hide
                                </button>
                            </div>
                            <input id="cc" x-ref="cc" name="cc" value="{{ old('cc') }}" class="w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100">
                        </div>
                        <div>
                            <label for="subject" class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Subject</label>
                            <input id="subject" name="subject" value="{{ old('subject') }}" class="w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100">
                            <x-input-error :messages="$errors->get('subject')" class="mt-2" />
                        </div>
                        <div>
                            <label for="body" class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Message</label>
                            <div class="min-h-72 rounded-xl border border-slate-200 bg-white shadow-sm focus-within:border-amber-500 focus-within:ring-1 focus-within:ring-amber-500 dark:border-zinc-800 dark:bg-zinc-950">
                                <textarea id="body" x-ref="body" x-model="bodyText" name="body" rows="6" placeholder="Write your message..." class="min-h-32 w-full resize-none border-0 bg-transparent text-sm shadow-none focus:border-0 focus:ring-0 dark:text-zinc-100"></textarea>
                                @if ($emailSignature)
                                    <div x-show="includeSignature" x-cloak class="px-3 pb-4 pt-1 text-sm text-slate-700 dark:text-zinc-200">
                                        <div class="flex items-start justify-between gap-4">
                                            <div>
                                                {!! $emailSignature['html'] !!}
                                            </div>
                                            <div class="flex shrink-0 gap-2">
                                                <button type="button" x-on:click="signatureSettingsOpen = true" class="rounded-lg px-3 py-1 text-xs font-semibold text-slate-500 hover:bg-slate-100 dark:text-zinc-400 dark:hover:bg-zinc-800">
                                                    Edit
                                                </button>
                                                <button type="button" x-on:click="includeSignature = false" class="rounded-lg px-3 py-1 text-xs font-semibold text-slate-500 hover:bg-slate-100 dark:text-zinc-400 dark:hover:bg-zinc-800">
                                                    Remove
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <div class="px-3 pb-4 pt-1">
                                        <button type="button" x-on:click="signatureSettingsOpen = true" class="rounded-lg border border-dashed border-slate-300 px-3 py-2 text-xs font-semibold text-slate-500 hover:bg-slate-50 dark:border-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-900">
                                            Create email signature
                                        </button>
                                    </div>
                                @endif
                                <div x-show="! includeSignature && signatureText" x-cloak class="px-3 pb-4">
                                    <button type="button" x-on:click="includeSignature = true" class="rounded-lg border border-dashed border-slate-300 px-3 py-2 text-xs font-semibold text-slate-500 hover:bg-slate-50 dark:border-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-900">
                                        Add saved signature
                                    </button>
                                </div>
                            </div>
                            <x-input-error :messages="$errors->get('body')" class="mt-2" />
                        </div>
                        <div x-show="attachmentNames.length || imageNames.length" x-cloak class="space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-300">
                            <template x-if="attachmentNames.length">
                                <p><span class="font-semibold">Attached:</span> <span x-text="attachmentNames.join(', ')"></span></p>
                            </template>
                            <template x-if="imageNames.length">
                                <p><span class="font-semibold">Images:</span> <span x-text="imageNames.join(', ')"></span></p>
                            </template>
                        </div>
                    </div>
                    <div x-show="showFormatting" x-cloak class="border-t border-slate-200 bg-slate-50 px-5 py-3 dark:border-zinc-800 dark:bg-zinc-950">
                        <div class="flex flex-wrap items-center gap-1 rounded-full bg-slate-100 p-1 text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">
                            <button type="button" class="rounded-full px-3 py-1.5 text-sm font-medium hover:bg-white hover:shadow-sm dark:hover:bg-zinc-700">Sans Serif</button>
                            <button type="button" x-on:click="applyFormat('bold')" title="Bold" class="inline-flex h-9 w-9 items-center justify-center rounded-full font-bold hover:bg-white hover:shadow-sm dark:hover:bg-zinc-700">B</button>
                            <button type="button" x-on:click="applyFormat('italic')" title="Italic" class="inline-flex h-9 w-9 items-center justify-center rounded-full italic hover:bg-white hover:shadow-sm dark:hover:bg-zinc-700">I</button>
                            <button type="button" x-on:click="applyFormat('underline')" title="Underline" class="inline-flex h-9 w-9 items-center justify-center rounded-full underline hover:bg-white hover:shadow-sm dark:hover:bg-zinc-700">U</button>
                            <button type="button" x-on:click="applyFormat('bullets')" title="Bulleted list" class="inline-flex h-9 w-9 items-center justify-center rounded-full hover:bg-white hover:shadow-sm dark:hover:bg-zinc-700">
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M5 6a1 1 0 1 1-2 0 1 1 0 0 1 2 0Zm2-1h10v2H7V5Zm-2 5a1 1 0 1 1-2 0 1 1 0 0 1 2 0Zm2-1h10v2H7V9Zm-2 5a1 1 0 1 1-2 0 1 1 0 0 1 2 0Zm2-1h10v2H7v-2Z"/></svg>
                            </button>
                            <button type="button" x-on:click="applyFormat('numbers')" title="Numbered list" class="inline-flex h-9 w-9 items-center justify-center rounded-full hover:bg-white hover:shadow-sm dark:hover:bg-zinc-700">
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M4 5h1v4H4V6H3V5h1Zm-.8 6.1c0-.8.7-1.4 1.6-1.4.9 0 1.6.6 1.6 1.4 0 .5-.2.9-.7 1.3l-.9.8h1.7v1H3.2v-.8l1.8-1.7c.3-.2.4-.4.4-.6 0-.3-.2-.5-.6-.5s-.6.2-.6.5h-1ZM8 5h9v2H8V5Zm0 4h9v2H8V9Zm0 4h9v2H8v-2Z"/></svg>
                            </button>
                            <button type="button" x-on:click="applyFormat('quote')" title="Quote" class="inline-flex h-9 w-9 items-center justify-center rounded-full hover:bg-white hover:shadow-sm dark:hover:bg-zinc-700">"</button>
                            <button type="button" x-on:click="applyFormat('remove')" title="Clear formatting" class="inline-flex h-9 w-9 items-center justify-center rounded-full hover:bg-white hover:shadow-sm dark:hover:bg-zinc-700">
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="m4.4 3.4 12.2 12.2-1 1-2.9-2.9-.6 1.8h-1.5l.9-3.1-3.8-3.8-2.1 6.9H4.1l2.5-8-3.2-3.1 1-1Zm5.4 1.1H16v1.4h-4.1l-.8 2.6-1.2-1.2.4-1.4H8.5l-.9-.9v-.5h2.2Z"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="relative flex items-center justify-between gap-3 border-t border-slate-200 px-5 py-4 dark:border-zinc-800">
                        <div x-show="showSendOptions" x-cloak x-transition class="absolute bottom-full left-5 z-10 mb-3 w-72 overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                            <button type="button" x-on:click="chooseScheduleSend()" class="flex w-full items-center gap-3 px-4 py-3 text-left text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:text-zinc-100 dark:hover:bg-zinc-800">
                                <svg class="h-5 w-5 text-blue-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 3a7 7 0 1 0 7 7h-1.4A5.6 5.6 0 1 1 10 4.4V7l3.5-3.3L10 .5V3Zm.8 4.5H9.4v3.3l3 1.8.7-1.2-2.3-1.4V7.5Z"/></svg>
                                Schedule send
                            </button>
                        </div>
                        <div x-show="showEmojiPicker" x-cloak x-transition class="absolute bottom-full left-40 z-10 mb-3 w-[22rem] max-w-[calc(100vw-3rem)] rounded-xl bg-white p-3 shadow-xl ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                            <input type="text" placeholder="Search emoji" class="mb-3 w-full rounded-full border-slate-200 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100">
                            <p class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-zinc-400">Recently used</p>
                            <div class="grid grid-cols-10 gap-1">
                                <template x-for="emoji in emojis" :key="emoji">
                                    <button type="button" x-on:click="pickEmoji(emoji)" class="flex h-8 w-8 items-center justify-center rounded-lg text-lg hover:bg-slate-100 dark:hover:bg-zinc-800" x-text="emoji"></button>
                                </template>
                            </div>
                        </div>
                        <div x-show="showMoreOptions" x-cloak x-transition class="absolute bottom-full left-48 z-10 mb-3 flex w-72 flex-col gap-2 rounded-xl bg-white p-3 shadow-xl ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                            <button type="button" x-on:click="insertText('\n\nPriority: High'); showMoreOptions = false" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-left text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-800">Mark high priority</button>
                            <button type="button" x-on:click="insertText('\n\nPlease reply when received.'); showMoreOptions = false" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-left text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-800">Request reply</button>
                        </div>
                        <div x-show="scheduleNote || composeNotice" x-cloak x-transition class="absolute bottom-full left-5 z-10 mb-3 max-w-sm rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-600 shadow-xl dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300">
                            <span x-text="scheduleNote || composeNotice"></span>
                        </div>
                        <div class="flex min-w-0 flex-1 flex-nowrap items-center gap-1 overflow-x-auto pb-1">
                            <div class="inline-flex shrink-0 overflow-hidden rounded-full bg-blue-600 text-white shadow-sm">
                                <button type="submit" x-bind:disabled="sendingEmail" class="inline-flex h-11 items-center gap-2 px-5 text-sm font-semibold hover:bg-blue-700 disabled:cursor-wait disabled:opacity-80">
                                    <svg x-show="sendingEmail" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path>
                                    </svg>
                                    <span x-text="sendingEmail ? 'Sending...' : 'Send'">Send</span>
                                </button>
                                <button type="button" x-bind:disabled="sendingEmail" x-on:click="toggleSendOptions()" title="More send options" aria-label="More send options" class="flex h-11 w-10 items-center justify-center border-l border-blue-500 hover:bg-blue-700 disabled:cursor-wait disabled:opacity-70">
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>

                            @php
                                $composeActions = [
                                    ['title' => 'Formatting options', 'action' => 'format', 'svg' => '<path d="M4 15.5 8.3 4h1.4L14 15.5h-1.4l-1.1-3H6.6l-1.1 3H4Zm3-4.2h4L9 5.8l-2 5.5Zm8.3 4.2V7.2h1.2v1.1a2.9 2.9 0 0 1 2.4-1.3v1.3a2.8 2.8 0 0 0-2.4 1.4v5.8h-1.2Z"/>'],
                                    ['title' => 'Attach files', 'action' => 'attach', 'svg' => '<path d="M7.8 16.5a4.3 4.3 0 0 1 0-6.1l5.2-5.2a3 3 0 0 1 4.2 4.2l-6.1 6.1a1.8 1.8 0 0 1-2.5-2.5l5.5-5.5.9.9-5.5 5.5a.5.5 0 0 0 .7.7l6.1-6.1a1.7 1.7 0 0 0-2.4-2.4l-5.2 5.2a3 3 0 0 0 4.2 4.2l5.2-5.2.9.9-5.2 5.2a4.3 4.3 0 0 1-6.1 0Z"/>'],
                                    ['title' => 'Insert link', 'action' => 'link', 'svg' => '<path d="M7.2 13.4H5.8a3.4 3.4 0 1 1 0-6.8h4v1.3h-4a2.1 2.1 0 1 0 0 4.2h1.4v1.3Zm1.1-2.7V9.3h5.4v1.4H8.3Zm1.7 2.7v-1.3h4.2a2.1 2.1 0 1 0 0-4.2H10V6.6h4.2a3.4 3.4 0 1 1 0 6.8H10Z"/>'],
                                    ['title' => 'Insert emoji', 'action' => 'emoji', 'svg' => '<path d="M10 17a7 7 0 1 1 0-14 7 7 0 0 1 0 14Zm0-1.3A5.7 5.7 0 1 0 10 4.3a5.7 5.7 0 0 0 0 11.4Zm-2.5-6.2a.9.9 0 1 1 0-1.8.9.9 0 0 1 0 1.8Zm5 0a.9.9 0 1 1 0-1.8.9.9 0 0 1 0 1.8ZM7 11.2h6a3.2 3.2 0 0 1-6 0Z"/>'],
                                    ['title' => 'Attach image', 'action' => 'image', 'svg' => '<path d="M4 5.2A1.2 1.2 0 0 1 5.2 4h9.6A1.2 1.2 0 0 1 16 5.2v9.6a1.2 1.2 0 0 1-1.2 1.2H5.2A1.2 1.2 0 0 1 4 14.8V5.2Zm1.4.2v7.4l2.4-2.4 2 2 2.7-3.4 2.1 2.7V5.4H5.4Zm9.2 8.9-2.1-2.8-2.5 3.1h4.6ZM5.4 14.6h2.8L7.8 12l-2.4 2.4v.2ZM8 8.3a1.1 1.1 0 1 1 0-2.2 1.1 1.1 0 0 1 0 2.2Z"/>'],
                                    ['title' => 'Add confidential note', 'action' => 'confidential', 'svg' => '<path d="M5.2 8.5V6.8a4.8 4.8 0 0 1 9.6 0v1.7H16V16H4V8.5h1.2Zm1.4 0h6.8V6.8a3.4 3.4 0 0 0-6.8 0v1.7Zm4.1 3.2a1 1 0 1 0-1.4 0v1.9h1.4v-1.9Z"/>'],
                                    ['title' => 'Insert signature', 'action' => 'signature', 'svg' => '<path d="M4 14.8c2.2-2.8 3.8-4.7 4.7-5.7 1-1.1 1.9-1.5 2.6-1.1.9.5.6 1.7.2 2.7-.2.5-.5 1.2-.3 1.3.4.2 1.6-1.1 2.3-2l1 .8c-1.7 2.2-3.1 3-4 2.4-1-.6-.6-1.8-.2-2.9.2-.5.4-1.1.3-1.2-.1-.1-.5.1-1 .7-.9 1-2.5 2.9-4.6 5.6L4 14.8Zm10.7-.2H18V16h-3.3v-1.4Z"/>'],
                                    ['title' => 'Insert calendar invite template', 'action' => 'calendar', 'svg' => '<path d="M6 3h1.4v2H13V3h1.4v2H17v12H3V5h3V3Zm9.6 5.4H4.4v7.2h11.2V8.4ZM4.4 6.4V7h11.2v-.6H4.4Z"/>'],
                                    ['title' => 'More options', 'action' => 'more', 'svg' => '<path d="M10 6.2a1.2 1.2 0 1 1 0-2.4 1.2 1.2 0 0 1 0 2.4Zm0 5a1.2 1.2 0 1 1 0-2.4 1.2 1.2 0 0 1 0 2.4Zm0 5a1.2 1.2 0 1 1 0-2.4 1.2 1.2 0 0 1 0 2.4Z"/>'],
                                ];
                            @endphp

                            @foreach ($composeActions as $action)
                                <button type="button" x-on:click="handleComposeAction('{{ $action['action'] }}')" title="{{ $action['title'] }}" aria-label="{{ $action['title'] }}" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 hover:text-slate-700 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100">
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        {!! $action['svg'] !!}
                                    </svg>
                                </button>
                            @endforeach
                        </div>

                        <button type="button" x-on:click="discardCompose()" title="Discard draft" aria-label="Discard draft" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 hover:text-rose-600 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-rose-300">
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M7 3.5h6l.6 1.5H17v1.4H3V5h3.4L7 3.5Zm-1.8 4h9.6l-.6 9H5.8l-.6-9Zm2.1 1.4.4 6.2h4.6l.4-6.2H7.3Z"/>
                            </svg>
                        </button>
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
                            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Configure one SiteGround mailbox and assign it to one CRM employee.</p>
                        </div>
                        <button type="button" x-on:click="settingsOpen = false" class="rounded-lg px-3 py-1 text-sm font-semibold text-slate-500 hover:bg-slate-100 dark:text-zinc-400 dark:hover:bg-zinc-800">Close</button>
                    </div>

                    <form method="POST" action="{{ $settingsAccount ? route('email.accounts.update', $settingsAccount) : route('email.accounts.store') }}" class="p-5">
                        @csrf
                        @if ($settingsAccount)
                            @method('PUT')
                        @endif

                        @if ($accounts->isNotEmpty())
                            <div class="mb-5">
                                <label for="admin_email_account" class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Mailbox</label>
                                <select id="admin_email_account"
                                        onchange="if (this.value) window.location.href = this.value"
                                        class="w-full rounded-xl border-slate-200 text-sm font-semibold shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100">
                                    @foreach ($accounts as $mailAccount)
                                        <option value="{{ route('email.index', ['account' => $mailAccount->id, 'folder' => $folder, 'settings' => 1]) }}" @selected($settingsAccount?->id === $mailAccount->id)>
                                            {{ $mailAccount->display_name ?: $mailAccount->email_address }} - {{ $mailAccount->email_address }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="mt-2 text-xs text-slate-500 dark:text-zinc-400">Choose which connected mailbox you want to update.</p>
                            </div>
                        @endif

                        <div class="mb-5">
                            <label for="user_id" class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Employee</label>
                            <select id="user_id" name="user_id" class="w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100">
                                <option value="">Choose employee</option>
                                @foreach ($users as $mailUser)
                                    @php($mailUserName = trim(($mailUser->first_name ?? '') . ' ' . ($mailUser->last_name ?? '')) ?: $mailUser->email)
                                    <option value="{{ $mailUser->id }}" @selected(old('user_id', $settingsAccount?->user_id) == $mailUser->id)>{{ $mailUserName }} - {{ $mailUser->email }}</option>
                                @endforeach
                            </select>
                            <p class="mt-2 text-xs text-slate-500 dark:text-zinc-400">Only this employee can open, refresh, and send from this mailbox. Admin can still manage all mailbox settings.</p>
                            <x-input-error :messages="$errors->get('user_id')" class="mt-2" />
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

        <template x-teleport="body">
            <div x-show="signatureSettingsOpen"
                 x-cloak
                 x-transition.opacity
                 class="crm-top-modal-backdrop flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm sm:p-6">
                <form method="POST" action="{{ route('email.signature.update', ['account' => $account?->id, 'folder' => $folder]) }}" x-on:submit="syncSignatureEditor()" data-no-page-loader class="crm-modal-panel max-h-[calc(100vh-2rem)] w-full max-w-6xl overflow-y-auto rounded-2xl bg-white shadow-2xl ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                    @csrf
                    @method('patch')
                    <input type="hidden" name="email_signature_html" x-bind:value="signatureHtml">
                    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-zinc-800">
                        <div>
                            <h2 class="font-bold text-slate-900 dark:text-zinc-100">Email Signature</h2>
                            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Create and format the signature appended to outgoing CRM emails.</p>
                        </div>
                        <button type="button" x-on:click="signatureSettingsOpen = false" class="rounded-lg px-3 py-1 text-sm font-semibold text-slate-500 hover:bg-slate-100 dark:text-zinc-400 dark:hover:bg-zinc-800">Close</button>
                    </div>
                    <div class="grid gap-0 border-b border-slate-200 dark:border-zinc-800 lg:grid-cols-[13rem_minmax(0,1fr)]">
                        <aside class="border-b border-slate-200 bg-slate-50 dark:border-zinc-800 dark:bg-zinc-950 lg:border-b-0 lg:border-r">
                            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-zinc-800">
                                <div>
                                    <p class="text-sm font-bold text-slate-900 dark:text-zinc-100">Signature</p>
                                    <p class="text-xs text-slate-500 dark:text-zinc-400">Outgoing messages</p>
                                </div>
                            </div>
                            <button type="button" class="flex w-full items-center justify-between bg-blue-50 px-4 py-4 text-left text-sm font-semibold text-slate-900 dark:bg-blue-400/10 dark:text-zinc-100">
                                <span>Signature email</span>
                                <span class="flex gap-2 text-slate-500 dark:text-zinc-400">
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M14.7 2.3 17.7 5.3 7.8 15.2 4 16l.8-3.8 9.9-9.9Z"/></svg>
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M7 3.5h6l.6 1.5H17v1.4H3V5h3.4L7 3.5Zm-1.8 4h9.6l-.6 9H5.8l-.6-9Z"/></svg>
                                </span>
                            </button>
                            <button type="button" class="flex w-full items-center justify-center gap-2 border-t border-slate-200 px-4 py-3 text-sm font-semibold text-blue-600 hover:bg-slate-100 dark:border-zinc-800 dark:hover:bg-zinc-900">
                                <span class="text-xl leading-none">+</span>
                                Create new
                            </button>
                        </aside>

                        <section class="min-w-0">
                            <div class="min-h-72 p-4">
                                <div x-ref="signatureEditor"
                                     x-init="$el.innerHTML = signatureHtml"
                                     x-on:input="syncSignatureEditor()"
                                     x-on:click="selectSignatureImage($event)"
                                     x-on:paste.debounce.100ms="syncSignatureEditor()"
                                     contenteditable="true"
                                     class="min-h-56 rounded-lg bg-white p-3 text-sm text-slate-900 outline-none focus:ring-2 focus:ring-blue-200 dark:bg-zinc-900 dark:text-zinc-100"></div>
                                <x-input-error :messages="$errors->get('email_signature_html')" class="mt-2" />
                            </div>
                            <div x-show="signatureImageSelected" x-cloak class="flex flex-wrap items-center gap-3 border-t border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-900 dark:border-blue-400/20 dark:bg-blue-400/10 dark:text-blue-100">
                                <span class="font-semibold">Image size</span>
                                <input type="range" min="80" max="500" step="10" x-model="selectedSignatureImageWidth" x-on:input="resizeSignatureImage($event.target.value)" class="w-48 accent-blue-600">
                                <span class="w-14 text-xs font-semibold" x-text="`${selectedSignatureImageWidth}px`"></span>
                                <button type="button" x-on:click="resizeSignatureImage(160)" class="rounded-lg bg-white px-3 py-1 text-xs font-semibold text-blue-700 shadow-sm ring-1 ring-blue-100 hover:bg-blue-50 dark:bg-zinc-900 dark:text-blue-100 dark:ring-blue-400/20">Small</button>
                                <button type="button" x-on:click="resizeSignatureImage(240)" class="rounded-lg bg-white px-3 py-1 text-xs font-semibold text-blue-700 shadow-sm ring-1 ring-blue-100 hover:bg-blue-50 dark:bg-zinc-900 dark:text-blue-100 dark:ring-blue-400/20">Medium</button>
                                <button type="button" x-on:click="resizeSignatureImage(360)" class="rounded-lg bg-white px-3 py-1 text-xs font-semibold text-blue-700 shadow-sm ring-1 ring-blue-100 hover:bg-blue-50 dark:bg-zinc-900 dark:text-blue-100 dark:ring-blue-400/20">Large</button>
                            </div>
                            <div class="flex flex-wrap items-center gap-1 border-t border-slate-200 bg-slate-50 px-3 py-2 text-slate-600 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-300">
                                <button type="button" class="rounded-lg px-2 py-1 text-sm font-semibold hover:bg-white dark:hover:bg-zinc-800">Sans Serif</button>
                                <button type="button" x-on:click="formatSignature('bold')" title="Bold" class="inline-flex h-9 w-9 items-center justify-center rounded-lg font-bold hover:bg-white dark:hover:bg-zinc-800">B</button>
                                <button type="button" x-on:click="formatSignature('italic')" title="Italic" class="inline-flex h-9 w-9 items-center justify-center rounded-lg italic hover:bg-white dark:hover:bg-zinc-800">I</button>
                                <button type="button" x-on:click="formatSignature('underline')" title="Underline" class="inline-flex h-9 w-9 items-center justify-center rounded-lg underline hover:bg-white dark:hover:bg-zinc-800">U</button>
                                <button type="button" x-on:click="formatSignature('foreColor', '#666666')" title="Gray text" class="inline-flex h-9 w-9 items-center justify-center rounded-lg hover:bg-white dark:hover:bg-zinc-800">A</button>
                                <button type="button" x-on:click="formatSignature('createLink', prompt('Paste the link URL') || '')" title="Link" class="inline-flex h-9 w-9 items-center justify-center rounded-lg hover:bg-white dark:hover:bg-zinc-800">
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M7.2 13.4H5.8a3.4 3.4 0 1 1 0-6.8h4v1.3h-4a2.1 2.1 0 1 0 0 4.2h1.4v1.3Zm1.1-2.7V9.3h5.4v1.4H8.3Zm1.7 2.7v-1.3h4.2a2.1 2.1 0 1 0 0-4.2H10V6.6h4.2a3.4 3.4 0 1 1 0 6.8H10Z"/></svg>
                                </button>
                                <button type="button" x-on:click="insertSignatureImage()" title="Insert image" class="inline-flex h-9 w-9 items-center justify-center rounded-lg hover:bg-white dark:hover:bg-zinc-800">
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M4 5.2A1.2 1.2 0 0 1 5.2 4h9.6A1.2 1.2 0 0 1 16 5.2v9.6a1.2 1.2 0 0 1-1.2 1.2H5.2A1.2 1.2 0 0 1 4 14.8V5.2Zm1.4.2v7.4l2.4-2.4 2 2 2.7-3.4 2.1 2.7V5.4H5.4Zm9.2 8.9-2.1-2.8-2.5 3.1h4.6ZM5.4 14.6h2.8L7.8 12l-2.4 2.4v.2ZM8 8.3a1.1 1.1 0 1 1 0-2.2 1.1 1.1 0 0 1 0 2.2Z"/></svg>
                                </button>
                                <button type="button" x-on:click="formatSignature('insertUnorderedList')" title="Bulleted list" class="inline-flex h-9 w-9 items-center justify-center rounded-lg hover:bg-white dark:hover:bg-zinc-800">
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M5 6a1 1 0 1 1-2 0 1 1 0 0 1 2 0Zm2-1h10v2H7V5Zm-2 5a1 1 0 1 1-2 0 1 1 0 0 1 2 0Zm2-1h10v2H7V9Zm-2 5a1 1 0 1 1-2 0 1 1 0 0 1 2 0Zm2-1h10v2H7v-2Z"/></svg>
                                </button>
                                <button type="button" x-on:click="formatSignature('removeFormat')" title="Remove formatting" class="inline-flex h-9 w-9 items-center justify-center rounded-lg hover:bg-white dark:hover:bg-zinc-800">
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="m4.4 3.4 12.2 12.2-1 1-2.9-2.9-.6 1.8h-1.5l.9-3.1-3.8-3.8-2.1 6.9H4.1l2.5-8-3.2-3.1 1-1Z"/></svg>
                                </button>
                            </div>
                        </section>
                    </div>

                    <div class="space-y-4 p-5">
                        <div>
                            <p class="text-sm font-bold text-slate-900 dark:text-zinc-100">Signature defaults</p>
                            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">For new emails use</label>
                                    <select class="w-full rounded-lg border-slate-300 text-sm dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100">
                                        <option>Signature email</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">On reply/forward use</label>
                                    <select class="w-full rounded-lg border-slate-300 text-sm dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100">
                                        <option>Signature email</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-zinc-300">
                            <input name="email_signature_enabled" type="checkbox" value="1" @checked(old('email_signature_enabled', auth()->user()->email_signature_enabled ?? true)) class="rounded border-slate-300 text-emerald-700 shadow-sm focus:ring-emerald-600 dark:border-zinc-700 dark:bg-zinc-900">
                            Automatically insert this signature in new compose messages.
                        </label>

                    </div>
                    <div class="flex justify-end gap-3 border-t border-slate-200 px-5 py-4 dark:border-zinc-800">
                        <button type="button" x-on:click="signatureSettingsOpen = false" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-zinc-800 dark:text-zinc-200">Cancel</button>
                        <button type="submit" class="rounded-xl bg-zinc-950 px-5 py-2 text-sm font-semibold text-amber-100 dark:bg-amber-400 dark:text-zinc-950">
                            Save Signature
                        </button>
                    </div>
                </form>
            </div>
        </template>
    </div>
</x-app-layout>
