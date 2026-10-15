<div class="relative h-56 w-full overflow-hidden bg-slate-100 dark:bg-zinc-900 lg:h-72"
     role="region" aria-label="{{ $reward->title }} photos"
     x-data="{
         active: 0,
         count: {{ $reward->media->count() }},
         timer: null,
         observer: null,
         visible: false,
         hovered: false,
         focused: false,
         playing: false,
         reducedMotion: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
         init() {
             this.observer = new IntersectionObserver(entries => {
                 this.visible = entries[0].isIntersecting;
                 this.start();
             }, { threshold: 0.3 });
             this.observer.observe(this.$el);
         },
         start() {
             this.stop();
             if (this.count > 1 && this.visible && !document.hidden && !this.hovered && !this.focused && !this.playing && !this.reducedMotion) {
                 this.timer = setInterval(() => this.select(this.active + 1), 4500);
             }
         },
         stop() {
             if (this.timer) clearInterval(this.timer);
             this.timer = null;
         },
         select(index) {
             this.$el.querySelectorAll('video').forEach(video => video.pause());
             this.playing = false;
             this.active = (index + this.count) % this.count;
         },
         destroy() {
             this.stop();
             this.observer?.disconnect();
         }
     }"
     x-on:visibilitychange.document="start()"
     x-on:mouseenter="hovered = true; stop()"
     x-on:mouseleave="hovered = false; start()"
     x-on:focusin="focused = true; stop()"
     x-on:focusout="focused = $el.contains($event.relatedTarget); start()">
    @foreach ($reward->media as $item)
        <div x-show="active === {{ $loop->index }}" @if (! $loop->first) x-cloak @endif
             class="absolute inset-0" x-transition.opacity.duration.500ms>
            @if ($item->type === 'video')
                <video src="{{ asset('storage/' . $item->path) }}" class="h-full w-full object-cover" muted controls preload="metadata"
                       x-on:play="playing = true; stop()" x-on:pause="playing = false; start()"
                       x-on:ended="select(active + 1); start()"></video>
            @else
                <img src="{{ asset('storage/' . $item->path) }}" alt="{{ $reward->title }} - photo {{ $loop->iteration }}"
                     class="h-full w-full object-cover" @if (! $loop->first) loading="lazy" @endif>
            @endif
        </div>
    @endforeach
</div>
