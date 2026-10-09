<x-app-layout>
    <x-slot name="header">
        Finance
    </x-slot>

    <div class="space-y-6"
         x-init="initPacketPreview()"
         x-data="{
             fields: @js($packet['fields']),
             fieldValues: @js($packet['fieldValues']),
             previewKind: @js($packet['previewKind']),
             previewUrl: @js($previewUrl),
             documentSigned: @js($packet['status'] === 'Signed'),
             currentPage: 1,
             totalPages: 1,
             zoom: 1,
             renderScale: 1.3,
             pdfLoading: false,
             pdfError: '',
             confirmSendOpen: false,
             sendForm: null,
             pages() {
                 return Array.from({ length: this.totalPages }, (_, index) => index + 1);
             },
             initPacketPreview() {
                 this.fields = this.fields.map((field) => ({
                     ...field,
                     page: Number(field.page || 1),
                     fontSize: Number(field.fontSize || 14),
                 }));

                 if (this.previewKind === 'pdf' && ! this.documentSigned) {
                     this.$nextTick(() => this.loadPdf());
                 }
             },
             fieldsForPage(page) {
                 return this.fields.filter((field) => Number(field.page || 1) === Number(page));
             },
             fieldValue(field) {
                 if (this.fieldValues[field.id]) return this.fieldValues[field.id];
                 return field.type === 'date' && this.documentSigned ? @js($packet['signedAt']?->format('m/d/Y')) : '';
             },
             loadPdfScript() {
                 if (window.pdfjsLib) {
                     return Promise.resolve(window.pdfjsLib);
                 }

                 if (window.crmPdfJsPromise) {
                     return window.crmPdfJsPromise;
                 }

                 window.crmPdfJsPromise = new Promise((resolve, reject) => {
                     const script = document.createElement('script');
                     script.src = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
                     script.onload = () => resolve(window.pdfjsLib);
                     script.onerror = () => reject(new Error('PDF preview tools could not load. Please check the connection and refresh.'));
                     document.head.appendChild(script);
                 });

                 return window.crmPdfJsPromise;
             },
             async loadPdf() {
                 this.pdfLoading = true;
                 this.pdfError = '';

                 try {
                     const pdfjsLib = await this.loadPdfScript();
                     pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
                     this.$el.__crmPdfDocument = await pdfjsLib.getDocument({ url: this.previewUrl }).promise;
                     this.totalPages = this.$el.__crmPdfDocument.numPages || 1;
                     await this.$nextTick();
                     await this.renderPdfPages();
                 } catch (error) {
                     this.pdfError = error.message || 'The PDF preview could not be loaded.';
                 } finally {
                     this.pdfLoading = false;
                 }
             },
             async renderPdfPages() {
                 const pdfDocument = this.$el.__crmPdfDocument;
                 if (! pdfDocument) return;

                 for (let pageNumber = 1; pageNumber <= this.totalPages; pageNumber += 1) {
                     const canvas = this.$el.querySelector(`[data-packet-pdf-canvas='${pageNumber}']`);
                     if (! canvas) continue;

                     const page = await pdfDocument.getPage(pageNumber);
                     const viewport = page.getViewport({ scale: this.renderScale });
                     const outputScale = Math.max(1, Math.min(2, window.devicePixelRatio || 1));
                     const context = canvas.getContext('2d');

                     canvas.width = Math.floor(viewport.width * outputScale);
                     canvas.height = Math.floor(viewport.height * outputScale);
                     canvas.style.width = `${viewport.width}px`;
                     canvas.style.height = `${viewport.height}px`;
                     context.setTransform(1, 0, 0, 1, 0, 0);
                     context.clearRect(0, 0, canvas.width, canvas.height);

                     await page.render({
                         canvasContext: context,
                         viewport,
                         transform: outputScale !== 1 ? [outputScale, 0, 0, outputScale, 0, 0] : null,
                     }).promise;
                 }
             },
             updateCurrentPage() {
                 const scroller = this.$refs.packetPdfScroller;
                 if (! scroller) return;

                 const scrollerTop = scroller.getBoundingClientRect().top;
                 let closestPage = this.currentPage;
                 let closestDistance = Infinity;

                 this.$el.querySelectorAll('[data-packet-page-surface]').forEach((page) => {
                     const distance = Math.abs(page.getBoundingClientRect().top - scrollerTop - 24);
                     if (distance < closestDistance) {
                         closestDistance = distance;
                         closestPage = Number(page.dataset.page || 1);
                     }
                 });

                 this.currentPage = closestPage;
             },
             changeZoom(amount) {
                 this.zoom = Math.max(0.65, Math.min(2, Number((this.zoom + amount).toFixed(2))));
             },
             requestSendConfirmation(event) {
                 event.preventDefault();
                 this.sendForm = event.target;
                 this.confirmSendOpen = true;
             },
             confirmSendContract() {
                 if (! this.sendForm) return;
                 this.confirmSendOpen = false;
                 this.sendForm.submit();
             },
         }">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">Contract Signature Packet</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                    Review, share, and track the CRM-hosted signing page.
                </p>
            </div>

            <a href="{{ route('finance.contracts.index') }}"
               class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">
                Back to Contracts
            </a>
        </div>

        @php
            $sentNotice = session('success')
                ?: ($packet['status'] === 'Sent' && $packet['sentAt']
                    ? 'Contract has been sent' . ($packet['recipientEmail'] ? ' to ' . $packet['recipientEmail'] : '') . '.'
                    : null);
        @endphp

        @if ($sentNotice)
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700 shadow-sm dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200">
                {{ $sentNotice }}
            </div>
        @endif

        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-sm font-bold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">{{ $packet['brandName'] }} Signature Request</p>
                    <h2 class="mt-2 max-w-4xl text-2xl font-bold text-slate-950 dark:text-zinc-50">{{ $packet['title'] }}</h2>
                    <p class="mt-2 text-sm text-slate-500 dark:text-zinc-400">Document ID: {{ $packet['documentId'] }}</p>
                </div>

                <span @class([
                    'inline-flex rounded-full px-3 py-1 text-sm font-bold',
                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-200' => $packet['status'] === 'Signed',
                    'bg-amber-100 text-amber-800 dark:bg-amber-400/15 dark:text-amber-200' => $packet['status'] === 'Sent',
                    'bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-zinc-300' => $packet['status'] === 'Draft',
                ])>
                    {{ $packet['status'] }}
                </span>
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(20rem,24rem)]">
                <div class="min-w-0 space-y-6">
                    <section class="rounded-xl bg-slate-50 p-5 dark:bg-zinc-950">
                        <h3 class="font-bold text-slate-900 dark:text-zinc-100">Overview</h3>
                        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-[10rem_1fr]">
                            <dt class="text-slate-500 dark:text-zinc-400">Status</dt>
                            <dd class="font-semibold text-slate-900 dark:text-zinc-100">{{ $packet['status'] }}</dd>
                            <dt class="text-slate-500 dark:text-zinc-400">Sender</dt>
                            <dd class="font-semibold text-slate-900 dark:text-zinc-100">
                                {{ $packet['senderName'] }}
                                @if ($packet['senderEmail'])
                                    <span class="text-slate-500 dark:text-zinc-400">({{ $packet['senderEmail'] }})</span>
                                @endif
                            </dd>
                            <dt class="text-slate-500 dark:text-zinc-400">Sent on</dt>
                            <dd class="font-semibold text-slate-900 dark:text-zinc-100">{{ $packet['sentAt']?->format('m/d/Y @ h:i A') ?: 'Not sent yet' }}</dd>
                            <dt class="text-slate-500 dark:text-zinc-400">Last activity</dt>
                            <dd class="font-semibold text-slate-900 dark:text-zinc-100">{{ $packet['lastActivityAt']?->format('m/d/Y @ h:i A') ?: '-' }}</dd>
                        </dl>
                    </section>

                    <section>
                        <h3 class="font-bold text-slate-900 dark:text-zinc-100">Recipients</h3>
                        <div class="mt-4 overflow-hidden rounded-xl border border-slate-200 dark:border-zinc-800">
                            <div class="grid gap-4 border-b border-slate-200 px-4 py-4 dark:border-zinc-800 sm:grid-cols-[1fr_14rem]">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-teal-100 text-sm font-bold text-teal-800 dark:bg-teal-400/15 dark:text-teal-200">
                                        {{ str($packet['signerName'])->explode(' ')->map(fn ($part) => str($part)->substr(0, 1))->take(2)->implode('') }}
                                    </span>
                                    <div>
                                        <p class="font-bold text-slate-900 dark:text-zinc-100">{{ $packet['signerName'] }}</p>
                                        <p class="text-sm text-slate-500 dark:text-zinc-400">{{ $packet['signerEmail'] ?: 'No email on record' }}</p>
                                    </div>
                                </div>
                                <div class="font-semibold text-slate-900 dark:text-zinc-100">
                                    {{ $packet['status'] === 'Signed' ? 'Signed' : 'Signer' }}
                                    <p class="text-sm font-normal text-slate-500 dark:text-zinc-400">{{ $packet['signedAt']?->format('m/d/Y @ h:i A') ?: 'Awaiting signature' }}</p>
                                </div>
                            </div>

                            @if ($packet['agentEmail'])
                                <div class="grid gap-4 px-4 py-4 sm:grid-cols-[1fr_14rem]">
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-slate-200 text-sm font-bold text-slate-700 dark:bg-zinc-800 dark:text-zinc-200">
                                            {{ str($packet['agentName'])->explode(' ')->map(fn ($part) => str($part)->substr(0, 1))->take(2)->implode('') }}
                                        </span>
                                        <div>
                                            <p class="font-bold text-slate-900 dark:text-zinc-100">{{ $packet['agentName'] }}</p>
                                            <p class="text-sm text-slate-500 dark:text-zinc-400">{{ $packet['agentEmail'] }}</p>
                                        </div>
                                    </div>
                                    <div class="font-semibold text-slate-900 dark:text-zinc-100">
                                        CC
                                        <p class="text-sm font-normal text-slate-500 dark:text-zinc-400">CRM copy recipient</p>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </section>

                    @if ($canManageContracts)
                        <section class="rounded-xl border border-slate-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-950">
                            <div class="flex flex-wrap items-center justify-between gap-4">
                                <div>
                                    <h3 class="font-bold text-slate-900 dark:text-zinc-100">Edit & Fill Fields</h3>
                                    <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                                        Open the full editor to place, drag, resize, and save fillable fields over the contract.
                                    </p>
                                </div>

                                <a href="{{ $editorUrl }}"
                                   class="inline-flex items-center justify-center rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-emerald-800 dark:bg-emerald-400 dark:text-zinc-950">
                                    Edit & Fill Fields
                                </a>
                            </div>
                        </section>
                    @endif

                    <section>
                        <h3 class="font-bold text-slate-900 dark:text-zinc-100">Preview</h3>
                        <div class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-zinc-800 dark:bg-zinc-950">
                            @if (! $packet['hasContractFile'])
                                <div class="px-6 py-16 text-center text-sm text-slate-500 dark:text-zinc-400">No contract file attached yet.</div>
                            @elseif ($packet['previewKind'] === 'pdf')
                                @if ($packet['status'] === 'Signed')
                                    <iframe src="{{ $previewUrl }}#toolbar=1&navpanes=0"
                                            title="Signed contract preview"
                                            class="h-[75vh] w-full bg-white"
                                            loading="lazy"></iframe>
                                @else
                                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-3 text-slate-900 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100">
                                    <div class="flex flex-wrap items-center gap-2 text-sm font-bold">
                                        <span>Page <span x-text="currentPage"></span> of <span x-text="totalPages"></span></span>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <button type="button"
                                                x-on:click="changeZoom(-0.1)"
                                                x-bind:disabled="pdfLoading"
                                                class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-bold shadow-sm disabled:cursor-not-allowed disabled:opacity-40 dark:border-zinc-700 dark:bg-zinc-900">
                                            -
                                        </button>
                                        <span class="min-w-16 text-center text-sm font-bold" x-text="`${Math.round(zoom * 100)}%`"></span>
                                        <button type="button"
                                                x-on:click="changeZoom(0.1)"
                                                x-bind:disabled="pdfLoading"
                                                class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-bold shadow-sm disabled:cursor-not-allowed disabled:opacity-40 dark:border-zinc-700 dark:bg-zinc-900">
                                            +
                                        </button>
                                    </div>
                                </div>

                                <div x-ref="packetPdfScroller"
                                     x-on:scroll.passive="updateCurrentPage()"
                                     class="max-h-[70vh] min-h-[42rem] overflow-auto bg-white p-0 dark:bg-zinc-950">
                                    <div x-show="pdfLoading" class="rounded-xl bg-white px-4 py-3 text-sm font-semibold text-slate-600 shadow">
                                        Loading PDF preview...
                                    </div>
                                    <div x-show="pdfError" x-text="pdfError" class="rounded-xl bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700 shadow"></div>
                                    <div x-show="! pdfLoading && ! pdfError"
                                         x-bind:style="`zoom:${zoom};`"
                                         class="mx-auto flex w-max flex-col gap-4">
                                        <template x-for="pageNumber in pages()" :key="pageNumber">
                                            <div data-packet-page-surface
                                                 x-bind:data-page="pageNumber"
                                                 class="relative border border-slate-300 bg-white">
                                                <canvas x-bind:data-packet-pdf-canvas="pageNumber" class="block bg-white"></canvas>
                                                <div class="pointer-events-none absolute inset-0">
                                                    <template x-for="field in fieldsForPage(pageNumber)" :key="field.id">
                                                        <div class="pointer-events-auto absolute"
                                                             x-bind:style="`left:${field.x}%; top:${field.y}%; width:${field.w}%; height:${field.h}%; font-size:${field.fontSize || 14}px;`">
                                                            <div x-bind:class="field.type === 'signature' || field.type === 'initials' ? 'signature-script' : ''"
                                                                 class="flex h-full w-full items-center justify-center overflow-hidden rounded border-2 border-dashed border-blue-500 bg-blue-50/80 px-2 font-bold leading-none text-slate-950"
                                                                 style="font-size: inherit;"
                                                                 x-text="field.type === 'checkbox' ? (fieldValue(field) ? 'Checked' : 'Checkbox') : (fieldValue(field) || field.label)">
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                                @endif
                            @elseif ($packet['previewKind'] === 'image')
                                <div class="relative bg-white">
                                    <img src="{{ $previewUrl }}" alt="Contract preview" class="mx-auto max-h-[70vh] bg-white object-contain">
                                    <div class="pointer-events-none absolute inset-0">
                                        <template x-for="field in fields" :key="field.id">
                                            <div class="pointer-events-auto absolute"
                                                 x-bind:style="`left:${field.x}%; top:${field.y}%; width:${field.w}%; height:${field.h}%; font-size:${field.fontSize || 14}px;`">
                                                <div x-bind:class="field.type === 'signature' || field.type === 'initials' ? 'signature-script' : ''"
                                                     class="flex h-full w-full items-center justify-center overflow-hidden rounded border-2 border-dashed border-blue-500 bg-blue-50/80 px-2 font-bold leading-none text-slate-950"
                                                     style="font-size: inherit;"
                                                     x-text="field.type === 'checkbox' ? (fieldValue(field) ? 'Checked' : 'Checkbox') : (fieldValue(field) || field.label)">
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            @else
                                <div class="px-6 py-16 text-center text-sm text-slate-500 dark:text-zinc-400">Preview is unavailable for this file type. Use download to review it.</div>
                            @endif
                        </div>
                    </section>
                </div>

                <aside class="min-w-0 space-y-4">
                    @if ($canManageContracts)
                        <div class="min-w-0 rounded-xl border border-slate-200 p-5 dark:border-zinc-800">
                            <h3 class="font-bold text-slate-900 dark:text-zinc-100">Contract File</h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                                {{ $packet['hasContractFile'] ? 'Replace the attached contract if needed.' : 'Attach the contract before placing fields.' }}
                            </p>
                            <form method="POST"
                                  action="{{ route('finance.contracts.documents.store', $endorsement) }}"
                                  enctype="multipart/form-data"
                                  class="mt-4 space-y-3">
                                @csrf
                                <input type="hidden" name="document_type" value="{{ \App\Models\SalesEndorsementDocument::TYPE_CONTRACT }}">
                                <input type="hidden" name="return_to_esign" value="1">
                                <input type="file"
                                       name="documents[]"
                                       required
                                       accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                                       class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-xs text-slate-700 shadow-sm file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-3 file:py-2 file:text-xs file:font-bold file:text-emerald-700 hover:file:bg-emerald-100 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:file:bg-emerald-400/10 dark:file:text-emerald-200">
                                <button type="submit"
                                        class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-800 shadow-sm hover:bg-slate-50 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100 dark:hover:bg-zinc-800">
                                    {{ $packet['hasContractFile'] ? 'Replace Contract' : 'Attach Contract' }}
                                </button>
                            </form>
                        </div>
                    @endif

                    @if ($canManageContracts)
                        <div class="min-w-0 rounded-xl border border-slate-200 p-5 dark:border-zinc-800">
                            <h3 class="font-bold text-slate-900 dark:text-zinc-100">Send Contract</h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                                Send the signing link to the author and CC the agent/manager.
                            </p>
                            <form method="POST"
                                  action="{{ $sendUrl }}"
                                  class="mt-4 space-y-4"
                                  x-on:submit="requestSendConfirmation($event)">
                                @csrf
                                <label class="block">
                                    <span class="text-xs font-bold uppercase text-slate-500 dark:text-zinc-400">Recipient</span>
                                    <input type="email"
                                           name="recipient_email"
                                           value="{{ old('recipient_email', $packet['recipientEmail']) }}"
                                           required
                                           placeholder="author@email.com"
                                           class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                </label>

                                <label class="block">
                                    <span class="text-xs font-bold uppercase text-slate-500 dark:text-zinc-400">CC</span>
                                    <textarea name="cc_emails"
                                              rows="4"
                                              placeholder="agent@email.com, manager@email.com"
                                              class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">{{ old('cc_emails', implode(', ', $packet['ccEmails'])) }}</textarea>
                                    <span class="mt-1 block text-xs text-slate-500 dark:text-zinc-400">Separate multiple emails with commas or new lines.</span>
                                </label>

                                <button type="submit"
                                        class="w-full rounded-xl bg-blue-600 px-4 py-3 text-sm font-bold text-white shadow-sm hover:bg-blue-700">
                                    Send Contract
                                </button>

                                @if ($packet['sentAt'])
                                    <p class="text-xs text-slate-500 dark:text-zinc-400">
                                        Last sent {{ $packet['sentAt']->format('M d, Y h:i A') }}
                                        @if ($packet['recipientEmail'])
                                            to {{ $packet['recipientEmail'] }}
                                        @endif
                                    </p>
                                @endif
                            </form>
                        </div>
                    @endif

                    <div class="min-w-0 rounded-xl border border-slate-200 p-5 dark:border-zinc-800">
                        <h3 class="font-bold text-slate-900 dark:text-zinc-100">Signing Link</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Share this link with the author. It expires in 30 days.</p>
                        <input id="signing-link"
                               type="text"
                               readonly
                               value="{{ $signUrl }}"
                               class="mt-4 w-full rounded-xl border-slate-300 bg-slate-50 text-xs text-slate-700 shadow-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-200">
                        <button type="button"
                                onclick="navigator.clipboard.writeText(document.getElementById('signing-link').value); this.textContent = 'Copied'; setTimeout(() => this.textContent = 'Copy Link', 1800);"
                                class="mt-3 w-full rounded-xl bg-emerald-700 px-4 py-3 text-sm font-bold text-white shadow-sm hover:bg-emerald-800 dark:bg-emerald-400 dark:text-zinc-950">
                            Copy Link
                        </button>

                        @if ($canManageContracts && ! $packet['sentAt'])
                            <form method="POST" action="{{ route('finance.contracts.update', $endorsement) }}" class="mt-3">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="contract_status" value="sent">
                                <button type="submit"
                                        class="w-full rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-800 shadow-sm hover:bg-amber-100 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-200">
                                    Mark Sent
                                </button>
                            </form>
                        @endif
                    </div>

                    @if ($packet['hasContractFile'])
                        <a href="{{ $downloadUrl }}"
                           class="flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-800 shadow-sm hover:bg-slate-50 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-100 dark:hover:bg-zinc-800">
                            Download Contract
                        </a>
                    @endif

                    @if ($packet['status'] === 'Signed')
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-5 dark:border-emerald-400/20 dark:bg-emerald-400/10">
                            <h3 class="font-bold text-emerald-900 dark:text-emerald-100">Signature Certificate</h3>
                            <dl class="mt-4 space-y-3 text-sm">
                                <div>
                                    <dt class="text-emerald-700 dark:text-emerald-300">Signer</dt>
                                    <dd class="font-semibold text-emerald-950 dark:text-emerald-50">{{ $packet['signerName'] }}</dd>
                                </div>
                                <div>
                                    <dt class="text-emerald-700 dark:text-emerald-300">Signed</dt>
                                    <dd class="font-semibold text-emerald-950 dark:text-emerald-50">{{ $packet['signedAt']?->format('m/d/Y @ h:i A') }}</dd>
                                </div>
                                <div>
                                    <dt class="text-emerald-700 dark:text-emerald-300">IP</dt>
                                    <dd class="font-semibold text-emerald-950 dark:text-emerald-50">{{ $packet['signerIp'] ?: '-' }}</dd>
                                </div>
                            </dl>
                        </div>
                    @endif
                </aside>
            </div>
        </div>

        <template x-teleport="body">
            <div x-cloak
                 x-show="confirmSendOpen"
                 x-on:keydown.escape.window="confirmSendOpen = false"
                 class="fixed inset-0 z-[2147483000] flex min-h-screen items-center justify-center bg-slate-950/60 px-4 py-6">
                <div x-on:click="confirmSendOpen = false" class="absolute inset-0"></div>
                <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl ring-1 ring-slate-200 dark:bg-zinc-950 dark:ring-zinc-800">
                    <h3 class="text-lg font-bold text-slate-950 dark:text-zinc-50">Send Contract?</h3>
                    <p class="mt-2 text-sm text-slate-500 dark:text-zinc-400">
                        This will email the signing link to the recipient and copy the selected CRM contacts.
                    </p>

                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button"
                                x-on:click="confirmSendOpen = false"
                                class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">
                            Cancel
                        </button>
                        <button type="button"
                                x-on:click="confirmSendContract()"
                                class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700">
                            Send Contract
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>
</x-app-layout>
