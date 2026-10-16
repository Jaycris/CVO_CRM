@php
    $removedMedia = collect(old('remove_media', []))->map(fn ($id) => (int) $id)->all();
    $mediaItems = $reward->media->reject(fn ($item) => in_array($item->id, $removedMedia, true))->map(fn ($item) => [
        'key' => 'existing-' . $item->id, 'id' => $item->id, 'source' => 'existing',
        'name' => $item->original_name ?: 'Reward media', 'type' => $item->type, 'url' => asset('storage/' . $item->path),
    ]);
    $oldOrder = array_flip(old('media_order', []));
    $mediaItems = $mediaItems->sortBy(fn ($item) => $oldOrder[$item['key']] ?? PHP_INT_MAX)->values()->all();
@endphp
<div class="min-w-0" x-data="{
    items: @js($mediaItems),
    removed: @js($removedMedia),
    move(index, direction) {
        const destination = index + direction;
        if (destination < 0 || destination >= this.items.length) return;
        this.items.splice(destination, 0, this.items.splice(index, 1)[0]);
    },
    makeMain(index) { this.items.unshift(this.items.splice(index, 1)[0]); },
    loadFiles(event) {
        this.items.filter(item => item.source === 'upload').forEach(item => URL.revokeObjectURL(item.url));
        this.items = this.items.filter(item => item.source === 'existing');
        Array.from(event.target.files).forEach((file, index) => this.items.push({
            key: `upload-${index}`, id: index, source: 'upload', name: file.name,
            type: file.type.startsWith('video/') ? 'video' : 'image', url: URL.createObjectURL(file)
        }));
    },
    remove(index) {
        const item = this.items.splice(index, 1)[0];
        if (item.source === 'existing') {
            this.removed.push(item.id);
        } else {
            URL.revokeObjectURL(item.url);
            const transfer = new DataTransfer();
            Array.from(this.$refs.files.files).filter((file, i) => i !== item.id).forEach(file => transfer.items.add(file));
            this.$refs.files.files = transfer.files;
            this.items.filter(entry => entry.source === 'upload' && entry.id > item.id).forEach(entry => {
                entry.id--; entry.key = `upload-${entry.id}`;
            });
        }
    },
    destroy() { this.items.filter(item => item.source === 'upload').forEach(item => URL.revokeObjectURL(item.url)); }
}">
    <div class="mt-3 grid gap-3" style="grid-template-columns: repeat(auto-fit, minmax(min(100%, 12rem), 1fr));">
        <template x-for="(item, index) in items" :key="item.key">
            <div class="min-w-0 overflow-hidden rounded-lg border border-slate-200 dark:border-zinc-700">
                <input type="hidden" name="media_order[]" :value="item.key">
                <div class="relative h-32 bg-slate-100 dark:bg-zinc-800">
                    <template x-if="item.type === 'image'"><img :src="item.url" :alt="item.name" class="h-full w-full object-contain"></template>
                    <template x-if="item.type === 'video'"><video :src="item.url" class="h-full w-full object-contain" muted controls preload="metadata"></video></template>
                    <span x-show="index === 0" class="absolute left-2 top-2 rounded bg-emerald-700 px-2 py-1 text-xs font-semibold text-white">Main</span>
                </div>
                <div class="p-2">
                    <p class="truncate text-xs text-slate-600 dark:text-zinc-300" :title="item.name" x-text="item.name"></p>
                    <div class="mt-2 flex items-center justify-between gap-1">
                        <button type="button" x-on:click="move(index, -1)" :disabled="index === 0" title="Move earlier" aria-label="Move earlier" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600 disabled:opacity-30 dark:border-zinc-700 dark:text-zinc-300">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m12 19-7-7 7-7M5 12h14" /></svg>
                        </button>
                        <button type="button" x-on:click="makeMain(index)" :disabled="index === 0" title="Set as main preview" aria-label="Set as main preview" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-emerald-700 disabled:opacity-30 dark:border-zinc-700 dark:text-emerald-300">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9Z" /></svg>
                        </button>
                        <button type="button" x-on:click="move(index, 1)" :disabled="index === items.length - 1" title="Move later" aria-label="Move later" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600 disabled:opacity-30 dark:border-zinc-700 dark:text-zinc-300">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m12 5 7 7-7 7M5 12h14" /></svg>
                        </button>
                        <button type="button" x-on:click="remove(index)" title="Remove photo" aria-label="Remove photo" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-rose-200 text-rose-700 dark:border-rose-400/30 dark:text-rose-200">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 6h18M9 6V4h6v2M5 6l1 14h12l1-14M10 10v6M14 10v6" /></svg>
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>
    <template x-for="id in removed" :key="id"><input type="hidden" name="remove_media[]" :value="id"></template>
    <div class="mt-4">
        <label for="media" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">Add Photos / Video</label>
        <input id="media" x-ref="files" name="media[]" type="file" multiple accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/quicktime,video/webm" x-on:change="loadFiles($event)"
               class="w-full rounded-lg border border-slate-300 px-4 py-3 text-sm shadow-sm file:mr-4 file:rounded-lg file:border-0 file:bg-zinc-950 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
    </div>
</div>
