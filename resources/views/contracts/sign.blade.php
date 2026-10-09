<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $brandSiteIcon = $packet['brandSiteIconUrl'] ?? $packet['brandLogoUrl'];
    @endphp
    <title>{{ $packet['brandName'] }} Signature Request | {{ $packet['title'] }}</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ $brandSiteIcon }}">
    <link rel="shortcut icon" type="image/png" href="{{ $brandSiteIcon }}">
    <link rel="apple-touch-icon" href="{{ $brandSiteIcon }}">
    <meta name="application-name" content="{{ $packet['brandName'] }}">
    <meta name="theme-color" content="{{ $packet['brandPrimaryColor'] }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Allura&family=Great+Vibes&family=Parisienne&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .signature-script {
            font-family: "Great Vibes", "Allura", "Parisienne", "Edwardian Script ITC", "Palace Script MT", "Kunstler Script", "Monotype Corsiva", "Segoe Script", cursive;
            font-style: italic;
            font-weight: 400;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-950 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-5">
            <div>
                @if ($packet['brandLogoUrl'])
                    <img src="{{ $packet['brandLogoUrl'] }}" alt="{{ $packet['brandName'] }}" class="h-12 w-auto max-w-[14rem] object-contain">
                @else
                    <div class="text-3xl font-black tracking-tight" style="color: {{ $packet['brandPrimaryColor'] }};">{{ $packet['brandName'] }}</div>
                @endif
                <p class="mt-1 text-sm font-semibold text-slate-500">Contract signature request</p>
            </div>
            <div class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700">
                Powered by {{ $packet['crmName'] }}
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-6 py-10"
          x-init="initPdfPreview()"
          x-data="{
              fields: @js($packet['fields']),
              fieldValues: @js(old('field_values', $packet['fieldValues'])),
              previewKind: @js($packet['previewKind']),
              previewUrl: @js($previewUrl),
              documentSigned: @js($packet['status'] === 'Signed'),
              currentPage: 1,
              totalPages: 1,
              zoom: 1,
              renderScale: 1.35,
              pdfLoading: false,
              pdfError: '',
              formError: '',
              signerName: @js(old('signer_name', $packet['signerName'])),
              signatureText: @js(old('signature_text', $packet['signatureText'] ?: $packet['signerName'])),
              signatureModalOpen: false,
              signatureModalField: null,
              signatureDraft: '',
              signatureFont: 'esign',
              signatureStyles: [
                  { key: 'esign', label: 'Signature Style', stack: 'Great Vibes, Allura, Parisienne, cursive' },
                  { key: 'formal', label: 'Formal', stack: 'Edwardian Script ITC, Palace Script MT, cursive' },
                  { key: 'classic', label: 'Classic', stack: 'Kunstler Script, Monotype Corsiva, cursive' },
                  { key: 'smooth', label: 'Smooth', stack: 'Segoe Script, Lucida Handwriting, cursive' },
              ],
              pages() {
                  return Array.from({ length: this.totalPages }, (_, index) => index + 1);
              },
              initPdfPreview() {
                  this.fields = this.fields.map((field) => ({
                      ...field,
                      page: Number(field.page || 1),
                      fontSize: Number(field.fontSize || 14),
                  }));

                  if (this.previewKind === 'pdf' && ! this.documentSigned) {
                      this.$nextTick(() => this.loadPdf());
                  }
              },
              visibleFields() {
                  return this.fields.filter((field) => Number(field.page || 1) === Number(this.currentPage));
              },
              fieldsForPage(page) {
                  return this.fields.filter((field) => Number(field.page || 1) === Number(page));
              },
              fieldValue(field) {
                  if (this.fieldValues[field.id]) return this.fieldValues[field.id];
                  return field.type === 'date' && this.documentSigned ? @js($packet['signedAt']?->format('m/d/Y')) : '';
              },
              setFieldValue(field, value) {
                  this.fieldValues[field.id] = value;
              },
              currentSignDate() {
                  return new Date().toLocaleDateString('en-US', {
                      month: '2-digit',
                      day: '2-digit',
                      year: 'numeric',
                  });
              },
              fillDateField(field) {
                  this.setFieldValue(field, this.currentSignDate());
              },
              signatureStampDate() {
                  if (this.documentSigned) {
                      return @js($packet['signedAt']?->copy()->timezone($packet['displayTimezone'])->format('m/d/Y @ h:i A'));
                  }

                  return new Date().toLocaleDateString('en-US');
              },
              signatureDisplaySize(field) {
                  const text = this.fieldValue(field) || field.label || '';
                  const baseSize = Number(field.fontSize || 14);
                  const width = Number(field.w || 24);
                  const textLength = Math.max(1, text.length);
                  const fittedSize = Math.floor((width * 14) / textLength);

                  return Math.max(16, Math.min(baseSize, fittedSize));
              },
              selectedSignatureStyle() {
                  return this.signatureStyles.find((style) => style.key === this.signatureFont) || this.signatureStyles[0];
              },
              signatureFontStack(key = null) {
                  const style = this.signatureStyles.find((signatureStyle) => signatureStyle.key === (key || this.signatureFont));
                  return style ? style.stack : this.signatureStyles[0].stack;
              },
              suggestedSignature(field) {
                  const name = (this.signerName || @js($packet['signerName'])).trim();

                  if (field?.type === 'initials') {
                      return name.split(/\s+/).filter(Boolean).map((part) => part[0]).join('').slice(0, 4).toUpperCase();
                  }

                  return name;
              },
              openSignatureModal(field) {
                  this.signatureModalField = field;
                  this.signatureDraft = this.fieldValues[field.id] || this.suggestedSignature(field);
                  this.signatureModalOpen = true;
                  this.$nextTick(() => this.$refs.signatureDraftInput?.focus());
              },
              applySignatureModal() {
                  if (! this.signatureModalField) return;

                  const value = (this.signatureDraft || this.suggestedSignature(this.signatureModalField)).trim();
                  this.setFieldValue(this.signatureModalField, value);

                  if (this.signatureModalField.type === 'signature') {
                      this.signatureText = value;
                  }

                  this.signatureModalOpen = false;
                  this.signatureModalField = null;
              },
              requiredFieldsComplete() {
                  const missingField = this.fields.find((field) => field.required && ! String(this.fieldValues[field.id] || '').trim());

                  if (! missingField) {
                      this.formError = '';
                      return true;
                  }

                  this.formError = `Please complete the ${missingField.label || 'required'} field before signing.`;
                  return false;
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
                      const canvas = this.$el.querySelector(`[data-pdf-canvas='${pageNumber}']`);
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
                  const scroller = this.$refs.pdfScroller;
                  if (! scroller) return;

                  const scrollerTop = scroller.getBoundingClientRect().top;
                  let closestPage = this.currentPage;
                  let closestDistance = Infinity;

                  this.$el.querySelectorAll('[data-page-surface]').forEach((page) => {
                      const distance = Math.abs(page.getBoundingClientRect().top - scrollerTop - 24);
                      if (distance < closestDistance) {
                          closestDistance = distance;
                          closestPage = Number(page.dataset.page || 1);
                      }
                  });

                  this.currentPage = closestPage;
              },
              async changeZoom(amount) {
                  this.zoom = Math.max(0.65, Math.min(2, Number((this.zoom + amount).toFixed(2))));
              },
          }">
        @if (session('success'))
            <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">
                Please complete the required signing fields.
            </div>
        @endif

        <h1 class="text-3xl font-bold leading-tight">{{ $packet['title'] }}</h1>

        <section class="mt-8">
            <h2 class="text-lg font-bold">Overview</h2>
            <div class="mt-4 rounded-xl bg-slate-100 p-5">
                <dl class="grid gap-3 text-sm sm:grid-cols-[10rem_1fr]">
                    <dt class="text-slate-500">Status</dt>
                    <dd class="font-semibold">
                        <span @class([
                            'rounded-full px-2.5 py-1',
                            'bg-emerald-100 text-emerald-700' => $packet['status'] === 'Signed',
                            'bg-amber-100 text-amber-800' => $packet['status'] !== 'Signed',
                        ])>{{ $packet['status'] }}</span>
                    </dd>
                    <dt class="text-slate-500">Sender</dt>
                    <dd class="font-semibold">
                        {{ $packet['senderName'] }}
                        @if ($packet['senderEmail'])
                            <span class="text-slate-500">({{ $packet['senderEmail'] }})</span>
                        @endif
                    </dd>
                    <dt class="text-slate-500">Sent on</dt>
                    <dd class="font-semibold">{{ $packet['sentAt']?->copy()->timezone($packet['displayTimezone'])->format('m/d/Y @ h:i A') ?: 'Pending' }}</dd>
                    <dt class="text-slate-500">Last activity</dt>
                    <dd class="font-semibold">{{ $packet['lastActivityAt']?->copy()->timezone($packet['displayTimezone'])->format('m/d/Y @ h:i A') ?: '-' }}</dd>
                </dl>
            </div>
        </section>

        @if ($packet['hasContractFile'])
            <div class="mt-6">
                <a href="{{ $downloadUrl }}"
                   class="inline-flex rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-800 shadow-sm hover:bg-slate-50">
                    Download
                </a>
            </div>
        @endif

        <section class="mt-10">
            <h2 class="text-lg font-bold">Recipients</h2>
            <div class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white">
                <div class="grid gap-4 border-b border-slate-200 px-5 py-4 sm:grid-cols-[1fr_14rem]">
                    <div class="flex items-center gap-3">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-teal-200 text-sm font-bold text-teal-900">
                            {{ str($packet['signerName'])->explode(' ')->map(fn ($part) => str($part)->substr(0, 1))->take(2)->implode('') }}
                        </span>
                        <div>
                            <p class="font-bold">{{ $packet['signerName'] }}</p>
                            <p class="text-sm text-slate-500">{{ $packet['signerEmail'] ?: 'No email on record' }}</p>
                        </div>
                    </div>
                    <div class="font-semibold">
                        {{ $packet['status'] === 'Signed' ? 'Signed' : 'Needs signature' }}
                        <p class="text-sm font-normal text-slate-500">{{ $packet['signedAt']?->copy()->timezone($packet['displayTimezone'])->format('m/d/Y @ h:i A') ?: 'Waiting' }}</p>
                    </div>
                </div>

                @if ($packet['agentEmail'])
                    <div class="grid gap-4 px-5 py-4 sm:grid-cols-[1fr_14rem]">
                        <div class="flex items-center gap-3">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-slate-200 text-sm font-bold text-slate-700">
                                {{ str($packet['agentName'])->explode(' ')->map(fn ($part) => str($part)->substr(0, 1))->take(2)->implode('') }}
                            </span>
                            <div>
                                <p class="font-bold">{{ $packet['agentName'] }}</p>
                                <p class="text-sm text-slate-500">{{ $packet['agentEmail'] }}</p>
                            </div>
                        </div>
                        <div class="font-semibold">
                            CC
                            <p class="text-sm font-normal text-slate-500">Received copy</p>
                        </div>
                    </div>
                @endif
            </div>
        </section>

        <section class="mt-10">
            <h2 class="text-lg font-bold">Preview</h2>
            <div class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white">
                @if (! $packet['hasContractFile'])
                    <div class="px-6 py-16 text-center text-sm text-slate-500">No contract file is attached yet.</div>
                @elseif ($packet['previewKind'] === 'pdf')
                    @if ($packet['status'] === 'Signed')
                        <iframe src="{{ $previewUrl }}#toolbar=1&navpanes=0"
                                title="Signed contract preview"
                                class="h-[75vh] w-full bg-white"
                                loading="lazy"></iframe>
                    @else
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-3 text-slate-900">
                        <div class="flex flex-wrap items-center gap-2 text-sm font-bold">
                            <span>Page <span x-text="currentPage"></span> of <span x-text="totalPages"></span></span>
                        </div>

                        <div class="flex items-center gap-2">
                            <button type="button"
                                    x-on:click="changeZoom(-0.1)"
                                    x-bind:disabled="pdfLoading"
                                    class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-bold shadow-sm disabled:cursor-not-allowed disabled:opacity-40">
                                -
                            </button>
                            <span class="min-w-16 text-center text-sm font-bold" x-text="`${Math.round(zoom * 100)}%`"></span>
                            <button type="button"
                                    x-on:click="changeZoom(0.1)"
                                    x-bind:disabled="pdfLoading"
                                    class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-bold shadow-sm disabled:cursor-not-allowed disabled:opacity-40">
                                +
                            </button>
                        </div>
                    </div>

                    <div x-ref="pdfScroller"
                         x-on:scroll.passive="updateCurrentPage()"
                         class="max-h-[75vh] min-h-[75vh] overflow-auto bg-white p-0">
                        <div x-show="pdfLoading" class="rounded-xl bg-white px-4 py-3 text-sm font-semibold text-slate-600 shadow">
                            Loading PDF preview...
                        </div>
                        <div x-show="pdfError" x-text="pdfError" class="rounded-xl bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700 shadow"></div>
                        <div x-show="! pdfLoading && ! pdfError"
                             x-bind:style="`zoom:${zoom};`"
                             class="mx-auto flex w-max flex-col gap-4">
                            <template x-for="pageNumber in pages()" :key="pageNumber">
                                <div data-page-surface
                                     x-bind:data-page="pageNumber"
                                     class="relative border border-slate-300 bg-white">
                                    <canvas x-bind:data-pdf-canvas="pageNumber" class="block bg-white"></canvas>
                                    @if (count($packet['fields']) > 0)
                                        <div class="pointer-events-none absolute inset-0">
                                            <template x-for="field in fieldsForPage(pageNumber)" :key="field.id">
                                                <div class="pointer-events-auto absolute"
                                                     x-bind:style="`left:${field.x}%; top:${field.y}%; width:${field.w}%; height:${field.h}%; font-size:${field.fontSize || 14}px;`">
                                                    <template x-if="documentSigned && field.type === 'signature'">
                                                        <div class="flex h-full w-full flex-col justify-center overflow-hidden rounded border border-blue-500 bg-blue-50/90 px-2 leading-none text-slate-950">
                                                            <span class="font-sans text-[7px] font-semibold leading-none text-slate-600">E-Signed by:</span>
                                                            <span class="signature-script whitespace-nowrap leading-none"
                                                                  x-bind:style="`font-size:${signatureDisplaySize(field)}px; font-family:${signatureFontStack()};`"
                                                                  x-text="fieldValue(field) || field.label"></span>
                                                            <span class="mt-0.5 font-sans text-[6px] font-semibold leading-none text-slate-600" x-text="signatureStampDate()"></span>
                                                        </div>
                                                    </template>
                                                    <template x-if="documentSigned && field.type !== 'signature'">
                                                        <div x-bind:class="field.type === 'signature' || field.type === 'initials' ? 'signature-script' : ''"
                                                             class="flex h-full w-full items-center rounded border border-blue-500 bg-blue-50/90 px-2 font-bold text-slate-950"
                                                             style="font-size: inherit;"
                                                             x-text="field.type === 'checkbox' ? (fieldValue(field) ? 'Checked' : 'Unchecked') : (fieldValue(field) || field.label)">
                                                        </div>
                                                    </template>
                                                    <template x-if="! documentSigned && field.type === 'checkbox'">
                                                        <label class="flex h-full w-full items-center justify-center rounded border-2 border-blue-500 bg-blue-50/90">
                                                            <input type="checkbox"
                                                                   x-bind:checked="!! fieldValues[field.id]"
                                                                   x-on:change="setFieldValue(field, $event.target.checked ? 'Checked' : '')"
                                                                   class="h-5 w-5 rounded border-slate-300 text-blue-600">
                                                        </label>
                                                    </template>
                                                    <template x-if="! documentSigned && (field.type === 'signature' || field.type === 'initials')">
                                                        <button type="button"
                                                                x-on:click="openSignatureModal(field)"
                                                                x-bind:required="!! field.required"
                                                                x-bind:class="fieldValue(field) ? 'text-slate-950' : 'text-slate-500'"
                                                                class="flex h-full w-full items-center rounded border-2 border-blue-500 bg-blue-50/90 px-2 text-left font-bold"
                                                                x-bind:style="fieldValue(field) ? `font-size: inherit; font-family:${signatureFontStack()};` : 'font-size: inherit;'">
                                                            <template x-if="field.type === 'signature' && fieldValue(field)">
                                                                <span class="flex min-w-0 flex-col leading-none">
                                                                    <span class="font-sans text-[7px] font-semibold leading-none text-slate-600">E-Signed by:</span>
                                                                    <span class="whitespace-nowrap leading-none"
                                                                          x-bind:style="`font-size:${signatureDisplaySize(field)}px;`"
                                                                          x-text="fieldValue(field)"></span>
                                                                    <span class="mt-0.5 font-sans text-[6px] font-semibold leading-none text-slate-600" x-text="signatureStampDate()"></span>
                                                                </span>
                                                            </template>
                                                            <template x-if="field.type !== 'signature' || ! fieldValue(field)">
                                                                <span class="truncate" x-text="fieldValue(field) || field.label"></span>
                                                            </template>
                                                        </button>
                                                    </template>
                                                    <template x-if="! documentSigned && field.type === 'date'">
                                                        <button type="button"
                                                                x-on:click="fillDateField(field)"
                                                                x-bind:class="fieldValue(field) ? 'text-slate-950' : 'text-slate-500'"
                                                                class="flex h-full w-full items-center justify-center rounded border-2 border-blue-500 bg-blue-50/90 px-2 text-center font-bold"
                                                                style="font-size: inherit;">
                                                            <span class="truncate" x-text="fieldValue(field) || field.label"></span>
                                                        </button>
                                                    </template>
                                                    <template x-if="! documentSigned && field.type !== 'checkbox' && field.type !== 'signature' && field.type !== 'initials' && field.type !== 'date'">
                                                        <input type="text"
                                                               x-model="fieldValues[field.id]"
                                                               x-bind:required="!! field.required"
                                                               x-bind:placeholder="field.label"
                                                               class="h-full w-full rounded border-2 border-blue-500 bg-blue-50/90 px-2 font-bold text-slate-950 placeholder:text-slate-500"
                                                               style="font-size: inherit;">
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    @endif
                                </div>
                            </template>
                        </div>
                    </div>
                    @endif
                @elseif ($packet['previewKind'] === 'image')
                    <div x-ref="fieldPage" class="relative bg-white">
                        <img src="{{ $previewUrl }}" alt="Contract preview" class="mx-auto max-h-[75vh] bg-white object-contain">
                        @if (count($packet['fields']) > 0)
                            <div class="pointer-events-none absolute inset-0">
                                <template x-for="field in fields" :key="field.id">
                                    <div class="pointer-events-auto absolute"
                                         x-bind:style="`left:${field.x}%; top:${field.y}%; width:${field.w}%; height:${field.h}%; font-size:${field.fontSize || 14}px;`">
                                        <template x-if="documentSigned && field.type === 'signature'">
                                            <div class="flex h-full w-full flex-col justify-center overflow-hidden rounded border border-blue-500 bg-blue-50/90 px-2 leading-none text-slate-950">
                                                <span class="font-sans text-[7px] font-semibold leading-none text-slate-600">E-Signed by:</span>
                                                <span class="signature-script whitespace-nowrap leading-none"
                                                      x-bind:style="`font-size:${signatureDisplaySize(field)}px; font-family:${signatureFontStack()};`"
                                                      x-text="fieldValue(field) || field.label"></span>
                                                <span class="mt-0.5 font-sans text-[6px] font-semibold leading-none text-slate-600" x-text="signatureStampDate()"></span>
                                            </div>
                                        </template>
                                        <template x-if="documentSigned && field.type !== 'signature'">
                                            <div x-bind:class="field.type === 'signature' || field.type === 'initials' ? 'signature-script' : ''"
                                                 class="flex h-full w-full items-center rounded border border-blue-500 bg-blue-50/90 px-2 font-bold text-slate-950"
                                                 style="font-size: inherit;"
                                                 x-text="field.type === 'checkbox' ? (fieldValue(field) ? 'Checked' : 'Unchecked') : (fieldValue(field) || field.label)">
                                            </div>
                                        </template>
                                        <template x-if="! documentSigned && field.type === 'checkbox'">
                                            <label class="flex h-full w-full items-center justify-center rounded border-2 border-blue-500 bg-blue-50/90">
                                                <input type="checkbox"
                                                       x-bind:checked="!! fieldValues[field.id]"
                                                       x-on:change="setFieldValue(field, $event.target.checked ? 'Checked' : '')"
                                                       class="h-5 w-5 rounded border-slate-300 text-blue-600">
                                            </label>
                                        </template>
                                        <template x-if="! documentSigned && (field.type === 'signature' || field.type === 'initials')">
                                            <button type="button"
                                                    x-on:click="openSignatureModal(field)"
                                                    x-bind:required="!! field.required"
                                                    x-bind:class="fieldValue(field) ? 'text-slate-950' : 'text-slate-500'"
                                                    class="flex h-full w-full items-center rounded border-2 border-blue-500 bg-blue-50/90 px-2 text-left font-bold"
                                                    x-bind:style="fieldValue(field) ? `font-size: inherit; font-family:${signatureFontStack()};` : 'font-size: inherit;'">
                                                <template x-if="field.type === 'signature' && fieldValue(field)">
                                                    <span class="flex min-w-0 flex-col leading-none">
                                                        <span class="font-sans text-[7px] font-semibold leading-none text-slate-600">E-Signed by:</span>
                                                        <span class="whitespace-nowrap leading-none"
                                                              x-bind:style="`font-size:${signatureDisplaySize(field)}px;`"
                                                              x-text="fieldValue(field)"></span>
                                                        <span class="mt-0.5 font-sans text-[6px] font-semibold leading-none text-slate-600" x-text="signatureStampDate()"></span>
                                                    </span>
                                                </template>
                                                <template x-if="field.type !== 'signature' || ! fieldValue(field)">
                                                    <span class="truncate" x-text="fieldValue(field) || field.label"></span>
                                                </template>
                                            </button>
                                        </template>
                                        <template x-if="! documentSigned && field.type === 'date'">
                                            <button type="button"
                                                    x-on:click="fillDateField(field)"
                                                    x-bind:class="fieldValue(field) ? 'text-slate-950' : 'text-slate-500'"
                                                    class="flex h-full w-full items-center justify-center rounded border-2 border-blue-500 bg-blue-50/90 px-2 text-center font-bold"
                                                    style="font-size: inherit;">
                                                <span class="truncate" x-text="fieldValue(field) || field.label"></span>
                                            </button>
                                        </template>
                                        <template x-if="! documentSigned && field.type !== 'checkbox' && field.type !== 'signature' && field.type !== 'initials' && field.type !== 'date'">
                                            <input type="text"
                                                   x-model="fieldValues[field.id]"
                                                   x-bind:required="!! field.required"
                                                   x-bind:placeholder="field.label"
                                                   class="h-full w-full rounded border-2 border-blue-500 bg-blue-50/90 px-2 font-bold text-slate-950 placeholder:text-slate-500"
                                                   style="font-size: inherit;">
                                        </template>
                                    </div>
                                </template>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="px-6 py-16 text-center text-sm text-slate-500">Preview is unavailable for this file type. Use download to review it.</div>
                @endif
            </div>
        </section>

        @if ($packet['status'] !== 'Signed')
            <section class="mt-10 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-bold">Sign Contract</h2>
                <p class="mt-2 text-sm text-slate-500">By signing below, you agree to the terms and conditions in the contract document.</p>

                <form id="sign-contract-form" method="POST" action="{{ $submitUrl }}" class="mt-6 space-y-5" x-on:submit="if (! requiredFieldsComplete()) $event.preventDefault()">
                    @csrf
                    <div x-show="formError"
                         x-text="formError"
                         class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">
                    </div>
                    <template x-for="field in fields" :key="`field-value-${field.id}`">
                        <input type="hidden"
                               x-bind:name="`field_values[${field.id}]`"
                               x-bind:value="fieldValues[field.id] || ''">
                    </template>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <label class="block">
                            <span class="text-sm font-semibold text-slate-700">Signer Name</span>
                            <input type="text" name="signer_name" x-model="signerName" required class="mt-2 w-full rounded-xl border-slate-300 shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                        </label>

                        <label class="block">
                            <span class="text-sm font-semibold text-slate-700">Email</span>
                            <input type="email" name="signer_email" value="{{ old('signer_email', $packet['signerEmail']) }}" class="mt-2 w-full rounded-xl border-slate-300 shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                        </label>
                    </div>

                    <input type="hidden" name="signature_text" x-bind:value="signatureText || signerName">

                    <label class="flex items-start gap-3 rounded-xl bg-slate-50 p-4 text-sm text-slate-700">
                        <input type="checkbox" name="accepted_terms" value="1" required class="mt-1 rounded border-slate-300 text-emerald-700 focus:ring-emerald-600">
                        <span>I confirm that I reviewed the contract and consent to sign it electronically.</span>
                    </label>

                    <div class="flex justify-end">
                        <button type="submit" class="rounded-xl bg-emerald-700 px-6 py-3 text-sm font-bold text-white shadow-sm hover:bg-emerald-800">
                            Sign Contract
                        </button>
                    </div>
                </form>
            </section>

            <div x-cloak
                 x-show="signatureModalOpen"
                 x-on:keydown.escape.window="signatureModalOpen = false"
                 class="fixed inset-0 z-[10000] flex items-center justify-center bg-slate-950/60 px-4 py-6">
                <div x-show="signatureModalOpen"
                     x-transition
                     x-on:click.outside="signatureModalOpen = false"
                     class="w-full max-w-xl rounded-2xl bg-white p-6 shadow-2xl">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-bold uppercase tracking-wide text-emerald-700">Type Signature</p>
                            <h2 class="mt-1 text-2xl font-bold text-slate-950" x-text="signatureModalField?.label || 'Signature'"></h2>
                        </div>
                        <button type="button"
                                x-on:click="signatureModalOpen = false"
                                class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-bold text-slate-600 hover:bg-slate-50">
                            Close
                        </button>
                    </div>

                    <label class="mt-6 block">
                        <span class="text-sm font-semibold text-slate-700">Your typed signature</span>
                        <input x-ref="signatureDraftInput"
                               type="text"
                               x-model="signatureDraft"
                               x-bind:style="`font-family:${signatureFontStack()};`"
                               class="signature-script mt-2 w-full rounded-xl border-slate-300 px-4 py-3 text-4xl shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                    </label>

                    <div class="mt-5 space-y-3">
                        <p class="text-xs font-bold uppercase tracking-wide text-blue-700">Suggested Signatures</p>
                        <template x-for="style in signatureStyles" :key="style.key">
                            <button type="button"
                                    x-on:click="signatureFont = style.key; signatureDraft = suggestedSignature(signatureModalField)"
                                    x-bind:class="signatureFont === style.key ? 'border-blue-500 bg-blue-100 ring-2 ring-blue-200' : 'border-blue-200 bg-blue-50 hover:bg-blue-100'"
                                    class="block w-full rounded-xl border px-4 py-4 text-left">
                                <span class="block text-xs font-bold uppercase tracking-wide text-blue-700" x-text="style.label"></span>
                                <span class="mt-2 block text-4xl text-slate-950"
                                      x-bind:style="`font-family:${signatureFontStack(style.key)}; font-style: italic; font-weight: 400;`"
                                      x-text="suggestedSignature(signatureModalField)"></span>
                            </button>
                        </template>
                    </div>

                    <div class="mt-6 flex flex-wrap justify-end gap-3">
                        <button type="button"
                                x-on:click="signatureModalOpen = false"
                                class="rounded-xl border border-slate-200 px-5 py-3 text-sm font-bold text-slate-700 hover:bg-slate-50">
                            Cancel
                        </button>
                        <button type="button"
                                x-on:click="applySignatureModal()"
                                class="rounded-xl bg-emerald-700 px-5 py-3 text-sm font-bold text-white shadow-sm hover:bg-emerald-800">
                            Use Signature
                        </button>
                    </div>
                </div>
            </div>
        @else
            <section class="mt-10 rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
                <div class="mx-auto max-w-5xl rounded-xl border-[6px] border-l-blue-500 border-r-emerald-300 border-t-cyan-400 border-b-emerald-300 p-9">
                    <h2 class="text-5xl font-normal leading-tight">Signature Certificate</h2>
                    <p class="mt-6 text-xl">Document completed by all parties on {{ $packet['signedAt']?->copy()->timezone($packet['displayTimezone'])->format('m/d/Y @ H:i T') }}</p>
                    <p class="mt-2 text-xl">Document ID: {{ $packet['documentId'] }}</p>

                    <div class="mt-10">
                        <h3 class="text-2xl font-normal">Sender information</h3>
                        <dl class="mt-5 grid max-w-3xl gap-2 text-lg sm:grid-cols-[8rem_1fr]">
                            <dt>Sent On:</dt>
                            <dd>{{ $packet['sentAt']?->copy()->timezone($packet['displayTimezone'])->format('m/d/Y @ H:i T') ?: '-' }}</dd>
                            <dt>Timezone:</dt>
                            <dd>{{ $packet['sentAt']?->copy()->timezone($packet['displayTimezone'])->format('T') ?: 'Eastern Time' }}</dd>
                            <dt>Sender:</dt>
                            <dd>
                                {{ $packet['senderName'] }}
                                @if ($packet['senderEmail'])
                                    ({{ $packet['senderEmail'] }})
                                @endif
                            </dd>
                        </dl>
                    </div>

                    <div class="mt-10 border-t border-slate-300 pt-5">
                        <div class="grid grid-cols-[1fr_22rem] gap-8 text-2xl font-normal">
                            <h3>Signer</h3>
                            <h3>Signature</h3>
                        </div>

                        <div class="mt-8 grid gap-8 rounded bg-slate-100 p-6 sm:grid-cols-[1fr_22rem]">
                            <div class="flex items-center gap-5">
                                <div class="relative flex h-24 w-24 shrink-0 items-center justify-center">
                                    <div class="h-20 w-16 rounded-t-full bg-blue-300"></div>
                                    <div class="absolute bottom-0 h-12 w-20 rounded-t-full bg-blue-300"></div>
                                    <div class="absolute bottom-1 right-0 flex h-9 w-9 items-center justify-center rounded-full bg-slate-950 text-white">
                                        <span class="text-lg font-black">✓</span>
                                    </div>
                                </div>

                                <div>
                                    <p class="text-xl font-bold">{{ $packet['signerName'] }}</p>
                                    <p>{{ $packet['signerEmail'] }}</p>
                                    <dl class="mt-5 grid gap-x-4 gap-y-1 text-sm sm:grid-cols-[5.5rem_1fr]">
                                        <dt>Received:</dt>
                                        <dd>{{ $packet['sentAt']?->copy()->timezone($packet['displayTimezone'])->format('m/d/Y @ H:i T') ?: '-' }}</dd>
                                        <dt>Viewed:</dt>
                                        <dd>{{ $packet['signedAt']?->copy()->timezone($packet['displayTimezone'])->format('m/d/Y @ H:i T') }}</dd>
                                        <dt>Signed:</dt>
                                        <dd>{{ $packet['signedAt']?->copy()->timezone($packet['displayTimezone'])->format('m/d/Y @ H:i T') }}</dd>
                                    </dl>
                                </div>
                            </div>

                            <div class="self-center">
                                <p class="signature-script bg-white px-4 py-3 text-5xl leading-none">{{ $packet['signatureText'] }}</p>
                                <p class="mt-4 text-sm">IP: {{ $packet['signerIp'] ?: '-' }}</p>
                            </div>
                        </div>
                    </div>

                    <p class="mt-5 text-lg">Page {{ max(1, (int) $packet['pageCount']) }} of {{ max(1, (int) $packet['pageCount']) }}</p>
                </div>
            </section>
        @endif
    </main>
</body>
</html>
