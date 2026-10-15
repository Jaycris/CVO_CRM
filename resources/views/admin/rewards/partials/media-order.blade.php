<div x-data="{
    items: @js($mediaItems ?? []),
    move(index, direction) {
        const destination = index + direction;
        if (destination < 0 || destination >= this.items.length) return;
        const item = this.items.splice(index, 1)[0];
        this.items.splice(destination, 0, item);
    },
    makeMain(index) {
        this.items.unshift(this.items.splice(index, 1)[0]);
    },
    loadFiles(event) {
        this.items.forEach(item => URL.revokeObjectURL(item.url));
        this.items = Array.from(event.target.files).map((file, index) => ({
            id: index, name: file.name, type: file.type.startsWith('video/') ? 'video' : 'image', url: URL.createObjectURL(file)
        }));
    }
}">
    @if ($uploadMode ?? false)
        <label for="media" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">Preview Photos / Video</label>
        <input id="media" name="media[]" type="file" multiple accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/quicktime,video/webm"
               x-on:change="loadFiles($event)"
               class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm shadow-sm file:mr-4 file:rounded-lg file:border-0 file:bg-zinc-950 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-black dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
    @endif
    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4" x-show="items.length" x-cloak>
        <template x-for="(item, index) in items" :key="item.id">
            <div class="min-w-0 overflow-hidden rounded-lg border border-slate-200 dark:border-zinc-700">
                <input type="hidden" name="{{ ($uploadMode ?? false) ? 'upload_order[]' : 'media_order[]' }}" :value="item.id">
                <div class="relative h-32 bg-slate-100 dark:bg-zinc-800">
                    <template x-if="item.type === 'image'">
                        <img :src="item.url" :alt="item.name" class="h-full w-full object-contain">
                    </template>
                    <template x-if="item.type === 'video'">
                        <video :src="item.url" class="h-full w-full object-contain" muted controls preload="metadata"></video>
                    </template>
                    <span x-show="index === 0" class="absolute left-2 top-2 rounded bg-emerald-700 px-2 py-1 text-xs font-semibold text-white">Main</span>
                </div>
                <div class="p-2">
                    <p class="truncate text-xs text-slate-600 dark:text-zinc-300" :title="item.name" x-text="item.name"></p>
                    <div class="mt-2 flex items-center justify-between gap-1">
                        <button type="button" x-on:click="move(index, -1)" :disabled="index === 0" title="Move earlier" aria-label="Move earlier"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600 disabled:opacity-30 dark:border-zinc-700 dark:text-zinc-300">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m12 19-7-7 7-7M5 12h14" /></svg>
                        </button>
                        <button type="button" x-on:click="makeMain(index)" :disabled="index === 0" title="Set as main preview" aria-label="Set as main preview"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-emerald-700 disabled:opacity-30 dark:border-zinc-700 dark:text-emerald-300">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9Z" /></svg>
                        </button>
                        <button type="button" x-on:click="move(index, 1)" :disabled="index === items.length - 1" title="Move later" aria-label="Move later"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600 disabled:opacity-30 dark:border-zinc-700 dark:text-zinc-300">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m12 5 7 7-7 7M5 12h14" /></svg>
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>
