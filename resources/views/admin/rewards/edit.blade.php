<x-app-layout>
    <x-slot name="header">Rewards</x-slot>
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">Edit Reward</h1>
            <a href="{{ route('admin.rewards.index') }}" class="text-sm font-semibold text-emerald-700 dark:text-emerald-300">Back to Rewards</a>
        </div>
        @if ($errors->any())
            <div role="alert" class="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-400/30 dark:bg-rose-400/10 dark:text-rose-200">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif
        <form method="POST" action="{{ route('admin.rewards.update', $reward) }}" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                <div>
                    <label for="title" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">Reward Title</label>
                    <input id="title" name="title" value="{{ old('title', $reward->title) }}" required maxlength="255" class="w-full rounded-lg border-slate-300 px-4 py-3 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                </div>
                <div>
                    <label for="requirement_amount" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">Required Monthly Target</label>
                    <input id="requirement_amount" name="requirement_amount" type="number" min="0" step="0.01" value="{{ old('requirement_amount', $reward->requirement_amount) }}" required class="w-full rounded-lg border-slate-300 px-4 py-3 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                </div>
                <div>
                    <label for="requirement_type" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">Requirement Type</label>
                    <select id="requirement_type" name="requirement_type" required class="w-full rounded-lg border-slate-300 px-4 py-3 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        @foreach (['sales_mtd' => 'Sales monthly MTD', 'sold_mined_leads' => 'Sold leads from mined leads', 'sold_verified_leads' => 'Sold leads from verified leads', 'completed_production_tasks' => 'Completed production tasks'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('requirement_type', $reward->requirement_type) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="reward_scope" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">Reward Type</label>
                    <select id="reward_scope" name="reward_scope" required class="w-full rounded-lg border-slate-300 px-4 py-3 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        @foreach (['individual' => 'Individual', 'team' => 'Team', 'company' => 'Whole Team'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('reward_scope', $reward->reward_scope) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            @foreach (['accommodation' => 'Description', 'requirements' => 'Additional Requirements'] as $name => $label)
                <div>
                    <label for="{{ $name }}" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">{{ $label }}</label>
                    <textarea id="{{ $name }}" name="{{ $name }}" rows="3" maxlength="1000" class="w-full rounded-lg border-slate-300 px-4 py-3 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">{{ old($name, $reward->{$name}) }}</textarea>
                </div>
            @endforeach
            <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                <div>
                    <label for="audience" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">Audience</label>
                    <select id="audience" name="audience" required class="w-full rounded-lg border-slate-300 px-4 py-3 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        @foreach (['all_users' => 'All Users', 'commission_eligible' => 'Eligible Commission', 'lead_generation' => 'Lead Generation', 'production' => 'Production'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('audience', $reward->audience) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="expires_in_days" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">Days to Claim After Unlock</label>
                    <input id="expires_in_days" name="expires_in_days" type="number" min="1" max="365" value="{{ old('expires_in_days', $reward->expires_in_days) }}" class="w-full rounded-lg border-slate-300 px-4 py-3 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                </div>
            </div>
            <label class="flex items-center gap-3 text-sm text-slate-700 dark:text-zinc-300">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $reward->is_active)) class="rounded border-slate-300 text-emerald-700 focus:ring-emerald-600">
                Post this reward
            </label>
            <section class="min-w-0 border-t border-slate-200 pt-5 dark:border-zinc-800">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-zinc-100">Photos / Video</h2>
                @include('admin.rewards.partials.edit-media', ['reward' => $reward])
            </section>
            <div class="flex flex-wrap justify-end gap-3 border-t border-slate-200 pt-5 dark:border-zinc-800">
                <a href="{{ route('admin.rewards.index') }}" class="rounded-lg border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700 dark:border-zinc-700 dark:text-zinc-300">Cancel</a>
                <button type="submit" class="rounded-lg bg-emerald-700 px-5 py-3 text-sm font-semibold text-white hover:bg-emerald-800">Save Changes</button>
            </div>
        </form>
    </div>
</x-app-layout>
