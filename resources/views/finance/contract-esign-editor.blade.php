<x-app-layout>
    <x-slot name="header">
        Finance
    </x-slot>

    <div
        class="space-y-4"
        x-init="initPdfPreview()"
        x-data="{
            fields: @js($packet['fields']),
            selectedId: null,
            dragging: null,
            resizing: null,
            previewKind: @js($packet['previewKind']),
            previewUrl: @js($previewUrl),
            currentPage: 1,
            totalPages: 1,
            zoom: 1,
            renderScale: 1.15,
            pdfLoading: false,
            pdfError: '',
            pages() {
                return Array.from({ length: this.totalPages }, (_, index) => index + 1);
            },
            initPdfPreview() {
                this.fields = this.fields.map((field) => ({
                    ...field,
                    page: Number(field.page || 1),
                    fontSize: Number(field.fontSize || 14),
                }));

                if (this.previewKind === 'pdf') {
                    this.$nextTick(() => this.loadPdf());
                }
            },
            visibleFields() {
                return this.fields.filter((field) => Number(field.page || 1) === Number(this.currentPage));
            },
            fieldsForPage(page) {
                return this.fields.filter((field) => Number(field.page || 1) === Number(page));
            },
            defaultPlacement(width, height) {
                const scroller = this.$refs.pdfScroller;
                const surfaces = Array.from(this.$el.querySelectorAll('[data-page-surface]'));

                if (scroller && surfaces.length > 0) {
                    const scrollerRect = scroller.getBoundingClientRect();
                    const centerX = scrollerRect.left + (scrollerRect.width / 2);
                    const centerY = scrollerRect.top + (scrollerRect.height / 2);
                    let target = surfaces.find((surface) => {
                        const rect = surface.getBoundingClientRect();
                        return centerY >= rect.top && centerY <= rect.bottom;
                    });

                    if (! target) {
                        target = surfaces.reduce((closest, surface) => {
                            const rect = surface.getBoundingClientRect();
                            const distance = Math.abs((rect.top + rect.height / 2) - centerY);
                            return ! closest || distance < closest.distance ? { surface, distance } : closest;
                        }, null)?.surface;
                    }

                    if (target) {
                        const rect = target.getBoundingClientRect();
                        const x = ((centerX - rect.left) / rect.width) * 100 - (width / 2);
                        const y = ((centerY - rect.top) / rect.height) * 100 - (height / 2);

                        return {
                            page: Number(target.dataset.page || this.currentPage || 1),
                            x: Math.max(0, Math.min(100 - width, Number(x.toFixed(2)))),
                            y: Math.max(0, Math.min(100 - height, Number(y.toFixed(2)))),
                        };
                    }
                }

                return {
                    page: this.currentPage,
                    x: Math.max(0, Math.min(100 - width, 50 - (width / 2))),
                    y: Math.max(0, Math.min(100 - height, 50 - (height / 2))),
                };
            },
            addField(type) {
                const labels = { signature: 'Signature', initials: 'Initials', date: 'Date Signed', text: 'Text', checkbox: 'Checkbox' };
                const width = type === 'checkbox' ? 8 : 24;
                const height = type === 'checkbox' ? 6 : 7;
                const placement = this.defaultPlacement(width, height);
                const field = {
                    id: `${Date.now()}-${Math.random().toString(16).slice(2)}`,
                    type,
                    label: labels[type] || 'Text',
                    page: placement.page,
                    x: placement.x,
                    y: placement.y,
                    w: width,
                    h: height,
                    fontSize: 14,
                    required: true,
                };
                this.fields.push(field);
                this.selectedId = field.id;
                this.currentPage = placement.page;
            },
            selectedField() {
                const field = this.fields.find((item) => item.id === this.selectedId) || null;
                if (field && ! field.fontSize) {
                    field.fontSize = 14;
                }
                return field;
            },
            removeSelected() {
                this.fields = this.fields.filter((field) => field.id !== this.selectedId);
                this.selectedId = null;
            },
            eventPoint(event) {
                const touch = event.touches?.[0] || event.changedTouches?.[0];

                return {
                    clientX: touch ? touch.clientX : event.clientX,
                    clientY: touch ? touch.clientY : event.clientY,
                };
            },
            startDrag(event, field, pageNumber = null) {
                event.preventDefault();
                event.stopPropagation();
                if (this.resizing) return;
                if (this.dragging) return;
                const page = event.currentTarget.closest('[data-page-surface]') || this.$refs.fieldPage;
                if (! page) return;
                const rect = page.getBoundingClientRect();
                const point = this.eventPoint(event);
                field.page = Number(pageNumber || page.dataset.page || this.currentPage || 1);
                this.selectedId = field.id;
                this.$el.__dragPageElement = page;
                this.dragging = {
                    id: field.id,
                    offsetX: point.clientX - (rect.left + (Number(field.x) / 100) * rect.width),
                    offsetY: point.clientY - (rect.top + (Number(field.y) / 100) * rect.height),
                };
                document.body.style.cursor = 'move';
                document.body.style.userSelect = 'none';
                if (event.pointerId !== undefined) {
                    try {
                        event.currentTarget.setPointerCapture?.(event.pointerId);
                    } catch (error) {
                        // Some browsers release pointer capture when the pointer leaves an embedded surface.
                    }
                }
                this.$el.__dragMoveHandler = (moveEvent) => this.drag(moveEvent);
                this.$el.__dragStopHandler = () => this.stopDrag();
                window.addEventListener('pointermove', this.$el.__dragMoveHandler);
                window.addEventListener('mousemove', this.$el.__dragMoveHandler);
                window.addEventListener('touchmove', this.$el.__dragMoveHandler, { passive: false });
                window.addEventListener('pointerup', this.$el.__dragStopHandler, { once: true });
                window.addEventListener('mouseup', this.$el.__dragStopHandler, { once: true });
                window.addEventListener('touchend', this.$el.__dragStopHandler, { once: true });
                window.addEventListener('touchcancel', this.$el.__dragStopHandler, { once: true });
            },
            drag(event) {
                if (! this.dragging) return;
                event.preventDefault();
                const page = this.$el.__dragPageElement || this.$refs.fieldPage;
                const field = this.fields.find((item) => item.id === this.dragging.id);
                if (! page || ! field) return;
                const rect = page.getBoundingClientRect();
                const point = this.eventPoint(event);
                const x = ((point.clientX - this.dragging.offsetX - rect.left) / rect.width) * 100;
                const y = ((point.clientY - this.dragging.offsetY - rect.top) / rect.height) * 100;
                field.x = Math.max(0, Math.min(100 - Number(field.w), Number(x.toFixed(2))));
                field.y = Math.max(0, Math.min(100 - Number(field.h), Number(y.toFixed(2))));
            },
            stopDrag() {
                if (this.$el.__dragMoveHandler) {
                    window.removeEventListener('pointermove', this.$el.__dragMoveHandler);
                    window.removeEventListener('mousemove', this.$el.__dragMoveHandler);
                    window.removeEventListener('touchmove', this.$el.__dragMoveHandler);
                }
                this.dragging = null;
                this.$el.__dragPageElement = null;
                this.$el.__dragMoveHandler = null;
                this.$el.__dragStopHandler = null;
                document.body.style.cursor = '';
                document.body.style.userSelect = '';
            },
            startResize(event, field) {
                event.preventDefault();
                event.stopPropagation();
                const page = event.currentTarget.closest('[data-page-surface]') || this.$refs.fieldPage;
                if (! page) return;
                const rect = page.getBoundingClientRect();
                const point = this.eventPoint(event);
                this.selectedId = field.id;
                this.$el.__resizePageElement = page;
                this.resizing = {
                    id: field.id,
                    startX: point.clientX,
                    startY: point.clientY,
                    startW: Number(field.w),
                    startH: Number(field.h),
                    pageW: rect.width,
                    pageH: rect.height,
                };
                if (event.pointerId !== undefined) {
                    event.currentTarget.setPointerCapture?.(event.pointerId);
                }
            },
            resize(event) {
                if (! this.resizing) return;
                event.preventDefault();
                const field = this.fields.find((item) => item.id === this.resizing.id);
                if (! field) return;
                const point = this.eventPoint(event);
                const deltaW = ((point.clientX - this.resizing.startX) / this.resizing.pageW) * 100;
                const deltaH = ((point.clientY - this.resizing.startY) / this.resizing.pageH) * 100;
                field.w = Math.max(6, Math.min(100 - Number(field.x), Number((this.resizing.startW + deltaW).toFixed(2))));
                field.h = Math.max(4, Math.min(100 - Number(field.y), Number((this.resizing.startH + deltaH).toFixed(2))));
            },
            stopResize() {
                this.resizing = null;
                this.$el.__resizePageElement = null;
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
                    this.$el.__pdfRenderTasks = {};
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

                const renderToken = `${Date.now()}-${Math.random().toString(16).slice(2)}`;
                this.$el.__pdfRenderToken = renderToken;
                this.pdfLoading = true;
                this.$el.__pdfRenderTasks = this.$el.__pdfRenderTasks || {};

                for (let pageNumber = 1; pageNumber <= this.totalPages; pageNumber += 1) {
                    const canvas = this.$el.querySelector(`[data-pdf-canvas='${pageNumber}']`);
                    if (! canvas) continue;
                    if (this.$el.__pdfRenderToken !== renderToken) break;

                    if (this.$el.__pdfRenderTasks[pageNumber]) {
                        this.$el.__pdfRenderTasks[pageNumber].cancel();
                        delete this.$el.__pdfRenderTasks[pageNumber];
                    }

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

                    const renderTask = page.render({
                        canvasContext: context,
                        viewport,
                        transform: outputScale !== 1 ? [outputScale, 0, 0, outputScale, 0, 0] : null,
                    });
                    this.$el.__pdfRenderTasks[pageNumber] = renderTask;

                    try {
                        await renderTask.promise;
                    } catch (error) {
                        if (error?.name !== 'RenderingCancelledException') {
                            throw error;
                        }
                    } finally {
                        if (this.$el.__pdfRenderTasks?.[pageNumber] === renderTask) {
                            delete this.$el.__pdfRenderTasks[pageNumber];
                        }
                    }
                }

                if (this.$el.__pdfRenderToken === renderToken) {
                    this.pdfLoading = false;
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
        }"
        x-on:pointermove.window="drag($event); resize($event)"
        x-on:pointerup.window="stopDrag(); stopResize()"
        x-on:mousemove.window="drag($event); resize($event)"
        x-on:mouseup.window="stopDrag(); stopResize()"
        x-on:touchmove.window="drag($event); resize($event)"
        x-on:touchend.window="stopDrag(); stopResize()"
        x-on:touchcancel.window="stopDrag(); stopResize()">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Edit & Fill</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-900 dark:text-zinc-100">{{ $packet['title'] }}</h1>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ $packetUrl }}"
                   class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-200">
                    Back to Packet
                </a>
                <form method="POST" action="{{ $fieldsUrl }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="fields" x-bind:value="JSON.stringify(fields)">
                    <button type="submit"
                            class="rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-emerald-800 dark:bg-emerald-400 dark:text-zinc-950">
                        Save Fields
                    </button>
                </form>
            </div>
        </div>

        <div class="grid min-h-[calc(100vh-12rem)] gap-4 xl:grid-cols-[1fr_18rem]">
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-300 dark:border-zinc-800 dark:bg-zinc-950">
                <div class="sticky top-0 z-20 flex flex-wrap items-center gap-2 border-b border-slate-300 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
                    <button type="button" x-on:click="addField('signature')" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800">Signature</button>
                    <button type="button" x-on:click="addField('initials')" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800">Initials</button>
                    <button type="button" x-on:click="addField('date')" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800">Date</button>
                    <button type="button" x-on:click="addField('text')" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800">Text</button>
                    <button type="button" x-on:click="addField('checkbox')" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800">Checkbox</button>
                </div>

                <div class="h-[calc(100vh-17rem)] overflow-auto p-6">
                    <div class="mx-auto max-w-5xl overflow-hidden rounded-xl bg-white shadow-2xl">
                        @if (! $packet['hasContractFile'])
                            <div class="flex h-[calc(100vh-20rem)] min-h-[40rem] items-center justify-center px-8 text-center text-sm text-slate-500">
                                No contract file attached yet. Go back to the packet and attach the contract file first.
                            </div>
                        @elseif ($packet['previewKind'] === 'pdf')
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
                                 class="h-[calc(100vh-22rem)] min-h-[44rem] overflow-auto bg-white p-0">
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
                                            <div class="pointer-events-none absolute inset-0">
                                                <template x-for="field in fieldsForPage(pageNumber)" :key="field.id">
                                                    <button type="button"
                                                            draggable="false"
                                                            x-on:dragstart.prevent
                                                            x-on:pointerdown.prevent="startDrag($event, field, pageNumber)"
                                                            x-on:mousedown.prevent="startDrag($event, field, pageNumber)"
                                                            x-on:touchstart.prevent="startDrag($event, field, pageNumber)"
                                                            x-bind:style="`left:${field.x}%; top:${field.y}%; width:${field.w}%; height:${field.h}%; font-size:${field.fontSize || 14}px;`"
                                                            x-bind:class="selectedId === field.id ? 'border-blue-600 bg-blue-400/50' : 'border-blue-500 bg-blue-300/40'"
                                                            class="pointer-events-auto absolute z-10 flex cursor-move select-none items-center justify-center overflow-hidden border-2 border-dashed px-2 font-bold leading-none text-slate-950 [touch-action:none]">
                                                        <span class="max-w-full truncate whitespace-nowrap" x-text="field.label"></span>
                                                        <span x-show="selectedId === field.id"
                                                              x-on:pointerdown.stop.prevent="startResize($event, field)"
                                                              x-on:mousedown.stop.prevent="startResize($event, field)"
                                                              x-on:touchstart.stop.prevent="startResize($event, field)"
                                                              class="absolute bottom-[-0.45rem] right-[-0.45rem] h-4 w-4 cursor-se-resize rounded-full border-2 border-white bg-blue-600 shadow"></span>
                                                    </button>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        @elseif ($packet['previewKind'] === 'image')
                            <div x-ref="fieldPage" class="relative">
                                <img src="{{ $previewUrl }}" alt="Contract full editor preview" class="mx-auto h-[calc(100vh-20rem)] min-h-[44rem] w-full object-contain">
                                <div class="pointer-events-none absolute inset-0">
                                    <template x-for="field in fields" :key="field.id">
                                        <button type="button"
                                                draggable="false"
                                                x-on:dragstart.prevent
                                                x-on:pointerdown.prevent="startDrag($event, field)"
                                                x-on:mousedown.prevent="startDrag($event, field)"
                                                x-on:touchstart.prevent="startDrag($event, field)"
                                                x-bind:style="`left:${field.x}%; top:${field.y}%; width:${field.w}%; height:${field.h}%; font-size:${field.fontSize || 14}px;`"
                                                x-bind:class="selectedId === field.id ? 'border-blue-600 bg-blue-400/50' : 'border-blue-500 bg-blue-300/40'"
                                                class="pointer-events-auto absolute z-10 flex cursor-move select-none items-center justify-center overflow-hidden border-2 border-dashed px-2 font-bold leading-none text-slate-950 [touch-action:none]">
                                            <span class="max-w-full truncate whitespace-nowrap" x-text="field.label"></span>
                                            <span x-show="selectedId === field.id"
                                                  x-on:pointerdown.stop.prevent="startResize($event, field)"
                                                  x-on:mousedown.stop.prevent="startResize($event, field)"
                                                  x-on:touchstart.stop.prevent="startResize($event, field)"
                                                  class="absolute bottom-[-0.45rem] right-[-0.45rem] h-4 w-4 cursor-se-resize rounded-full border-2 border-white bg-blue-600 shadow"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        @else
                            <div class="flex h-[calc(100vh-20rem)] min-h-[40rem] items-center justify-center text-sm text-slate-500">Preview is unavailable for this file type.</div>
                        @endif
                    </div>
                </div>
            </div>

            <aside class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-950">
                <h2 class="text-lg font-bold text-slate-900 dark:text-zinc-100">Fields</h2>
                <template x-if="! selectedField()">
                    <p class="mt-4 text-sm text-slate-500 dark:text-zinc-400">Select or add a field to adjust it.</p>
                </template>

                <template x-if="selectedField()">
                    <div class="mt-4 space-y-4">
                        <label class="block">
                            <span class="text-xs font-bold uppercase text-slate-500 dark:text-zinc-400">Label</span>
                            <input type="text" x-model="selectedField().label" class="mt-1 w-full rounded-lg border-slate-300 text-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-slate-500 dark:text-zinc-400">Font Size</span>
                            <input type="range" min="8" max="48" step="1" x-model.number="selectedField().fontSize" class="mt-3 w-full accent-emerald-700">
                            <input type="number" min="8" max="48" step="1" x-model.number="selectedField().fontSize" class="mt-2 w-full rounded-lg border-slate-300 text-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                        </label>

                        <label class="flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-zinc-200">
                            <input type="checkbox" x-model="selectedField().required" class="rounded border-slate-300 text-emerald-700">
                            Required
                        </label>

                        <button type="button" x-on:click="removeSelected()" class="w-full rounded-xl border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-bold text-rose-700 hover:bg-rose-100 dark:border-rose-400/30 dark:bg-rose-400/10 dark:text-rose-200">
                            Remove Field
                        </button>
                    </div>
                </template>
            </aside>
        </div>
    </div>
</x-app-layout>
