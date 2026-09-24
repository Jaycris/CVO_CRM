<x-app-layout>
    <x-slot name="header">
        Reward Claims
    </x-slot>

    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">Reward Claims</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                    Review pending reward claim requests and completed claimed rewards.
                </p>
            </div>

            <a href="{{ route('admin.rewards.index') }}"
               class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">
                Manage Rewards
            </a>
        </div>

        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200">
                {{ session('success') }}
            </div>
        @endif

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.rewards.claims', ['status' => 'pending']) }}"
               @class([
                   'rounded-xl px-4 py-2 text-sm font-semibold transition',
                   'bg-emerald-700 text-white shadow-sm' => $status === 'pending',
                   'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800' => $status !== 'pending',
               ])>
                Pending Claims
                <span class="ml-1 rounded-full bg-white/20 px-2 py-0.5 text-xs">{{ $pendingCount }}</span>
            </a>

            <a href="{{ route('admin.rewards.claims', ['status' => 'claimed']) }}"
               @class([
                   'rounded-xl px-4 py-2 text-sm font-semibold transition',
                   'bg-emerald-700 text-white shadow-sm' => $status === 'claimed',
                   'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800' => $status !== 'claimed',
               ])>
                Claimed Rewards
                <span class="ml-1 rounded-full bg-white/20 px-2 py-0.5 text-xs">{{ $claimedCount }}</span>
            </a>
        </div>

        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
            <div class="border-b border-slate-200 px-6 py-4 dark:border-zinc-800">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-zinc-100">
                    {{ $status === 'pending' ? 'Pending Claims' : 'Claimed Rewards' }}
                </h2>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-zinc-950 dark:text-zinc-400">
                        <tr>
                            <th class="px-5 py-3">Reward</th>
                            <th class="px-5 py-3">User</th>
                            <th class="px-5 py-3">Unlocked MTD</th>
                            <th class="px-5 py-3">Requested</th>
                            <th class="px-5 py-3">Claim Deadline</th>
                            <th class="px-5 py-3">Status</th>
                            @if ($status === 'pending')
                                <th class="px-5 py-3 text-right">Action</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-zinc-800">
                        @forelse ($claims as $claim)
                            @php
                                $userName = trim(($claim->user?->first_name ?? '') . ' ' . ($claim->user?->last_name ?? '')) ?: $claim->user?->email;
                                $isExpired = $claim->expires_at && $claim->expires_at->isPast() && ! $claim->claimed_at;
                            @endphp
                            <tr class="hover:bg-slate-50 dark:hover:bg-zinc-800/60">
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        @php($media = $claim->reward?->media?->first())
                                        @if ($media?->type === 'video')
                                            <video src="{{ asset('storage/' . $media->path) }}" class="h-12 w-16 rounded-lg object-cover" muted></video>
                                        @elseif ($media)
                                            <img src="{{ asset('storage/' . $media->path) }}" alt="{{ $claim->reward?->title }}" class="h-12 w-16 rounded-lg object-cover">
                                        @else
                                            <div class="flex h-12 w-16 items-center justify-center rounded-lg bg-slate-100 text-xs text-slate-400 dark:bg-zinc-800">Reward</div>
                                        @endif
                                        <div>
                                            <p class="font-semibold text-slate-900 dark:text-zinc-100">{{ $claim->reward?->title ?: 'Deleted reward' }}</p>
                                            <p class="mt-0.5 text-xs text-slate-500 dark:text-zinc-400">
                                                {{ $claim->reward?->reward_scope === 'team' ? 'Team Reward' : 'Individual Reward' }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="font-semibold text-slate-900 dark:text-zinc-100">{{ $userName ?: 'Unknown user' }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500 dark:text-zinc-400">{{ $claim->user?->email }}</p>
                                </td>
                                <td class="px-5 py-4 font-semibold text-slate-900 dark:text-zinc-100">
                                    ${{ number_format((float) $claim->progress_amount, 2) }}
                                </td>
                                <td class="px-5 py-4 text-slate-600 dark:text-zinc-300">
                                    {{ $claim->claim_requested_at?->format('M d, Y h:i A') ?: '-' }}
                                </td>
                                <td class="px-5 py-4 text-slate-600 dark:text-zinc-300">
                                    {{ $claim->expires_at?->format('M d, Y') ?: 'No deadline' }}
                                </td>
                                <td class="px-5 py-4">
                                    @if ($claim->claimed_at)
                                        <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-200">
                                            Claimed {{ $claim->claimed_at->format('M d, Y') }}
                                        </span>
                                    @elseif ($isExpired)
                                        <span class="rounded-full bg-rose-50 px-2.5 py-1 text-xs font-bold text-rose-700 dark:bg-rose-400/10 dark:text-rose-200">
                                            Expired
                                        </span>
                                    @else
                                        <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700 dark:bg-amber-400/10 dark:text-amber-200">
                                            Pending
                                        </span>
                                    @endif
                                </td>
                                @if ($status === 'pending')
                                    <td class="px-5 py-4 text-right">
                                        <form method="POST" action="{{ route('admin.rewards.claims.mark-claimed', $claim) }}">
                                            @csrf
                                            @method('PUT')
                                            <button type="submit"
                                                    class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-800">
                                                Mark as Claimed
                                            </button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $status === 'pending' ? 7 : 6 }}" class="px-6 py-12 text-center text-sm text-slate-500 dark:text-zinc-400">
                                    No {{ $status === 'pending' ? 'pending claim requests' : 'claimed rewards' }} yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($claims->hasPages())
                <div class="border-t border-slate-200 px-6 py-4 dark:border-zinc-800">
                    {{ $claims->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
