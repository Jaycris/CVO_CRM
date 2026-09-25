<x-app-layout>
    <x-slot name="header">
        Claim Reward
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6">
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

        <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
            @php($media = $reward->media->first())
            @if ($media)
                @if ($media->type === 'video')
                    <video src="{{ asset('storage/' . $media->path) }}" class="h-72 w-full bg-slate-100 object-cover dark:bg-zinc-950" muted controls></video>
                @else
                    <img src="{{ asset('storage/' . $media->path) }}" alt="{{ $reward->title }}" class="h-72 w-full bg-slate-100 object-cover dark:bg-zinc-950">
                @endif
            @endif

            <div class="p-6">
                <span class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold uppercase tracking-wide text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-200">
                    Congratulations
                </span>

                <h1 class="mt-4 text-2xl font-bold text-slate-950 dark:text-white">
                    Congratulations on unlocking {{ $reward->title }}.
                </h1>

                @if ($reward->accommodation)
                    <p class="mt-3 whitespace-pre-wrap break-words text-justify text-sm leading-6 text-slate-600 dark:text-zinc-300">{{ $reward->accommodation }}</p>
                @endif

                @if (! $unlock->claimed_at && $unlock->expires_at && ! $unlock->expires_at->isPast())
                    <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-100">
                        Please claim this reward before {{ $unlock->expires_at->format('M d, Y') }}.
                    </div>
                @endif

                @if ($unlock->claimed_at)
                    <div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200">
                        This reward was already claimed on {{ $unlock->claimed_at->format('M d, Y') }}.
                    </div>
                @elseif ($unlock->expires_at && $unlock->expires_at->isPast())
                    <div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 dark:border-rose-400/30 dark:bg-rose-400/10 dark:text-rose-200">
                        This reward claim window has already expired.
                    </div>
                @elseif ($unlock->claim_requested_at)
                    <div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200">
                        Your claim request has been sent to Admin. They will reach out to you for the information.
                    </div>
                @elseif ($sameTierClaim)
                    <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-100">
                        You already selected {{ $sameTierClaim->reward?->title ?? 'another reward' }} for this same requirement this month. You can choose again next month if this reward is still available.
                    </div>

                    @if ($sameTierClaim->reward)
                        <div class="mt-5">
                            <a href="{{ route('rewards.claim.show', $sameTierClaim->reward) }}"
                               class="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-700 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800">
                                View Selected Reward
                            </a>
                        </div>
                    @endif
                @else
                    <p class="mt-5 text-sm text-slate-500 dark:text-zinc-400">
                        If you want to claim this reward, send a claim request and Admin will reach out to you for the information.
                    </p>

                    <form method="POST" action="{{ route('rewards.claim.request', $reward) }}" class="mt-5">
                        @csrf
                        <button type="submit"
                                class="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-700 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800">
                            Send Claim Request
                        </button>
                    </form>
                @endif
            </div>
        </section>
    </div>
</x-app-layout>
