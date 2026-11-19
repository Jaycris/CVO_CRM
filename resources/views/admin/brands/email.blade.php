<x-app-layout>
    <x-slot name="header">Brand Email Sender</x-slot>
    <div class="mx-auto max-w-3xl space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold">{{ $brand->imprint_name }} — Email Sender</h1>
                <p class="mt-2 text-sm text-slate-500">Configure the sending mailbox for this brand’s contracts, invitations, and project completion emails.</p>
            </div>
            <a href="{{ route('admin.brands.index') }}" class="rounded-xl border px-4 py-2">Back</a>
        </div>
        @if (session('success'))
            <div class="rounded-xl bg-emerald-50 p-4 text-emerald-800">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div role="alert" class="rounded-xl bg-rose-50 p-4 text-rose-700">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ route('admin.brands.email.update', $brand) }}"
              class="space-y-5 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800"
              x-data="{ enabled: @js((bool) old('is_enabled', $setting?->is_enabled ?? false)) }">
            @csrf
            @method('PUT')
            <input type="hidden" name="is_enabled" value="0">
            <label class="flex items-center gap-3 font-semibold">
                <input type="checkbox" name="is_enabled" value="1" x-model="enabled" class="rounded text-emerald-700">
                Enable this brand’s sender
            </label>
            <p class="text-sm text-slate-500">When disabled, the CRM uses the existing shared brand mailbox or system mail configuration.</p>
            <div class="grid gap-5 md:grid-cols-2">
                @foreach (['display_name' => 'Sender Display Name', 'email_address' => 'Sender Email Address', 'reply_to_address' => 'Reply-to Email (optional)', 'smtp_host' => 'SMTP Host', 'username' => 'SMTP Username'] as $field => $label)
                    <div>
                        <label for="{{ $field }}" class="mb-2 block text-sm font-medium">{{ $label }}</label>
                        <input id="{{ $field }}" name="{{ $field }}" type="{{ in_array($field, ['email_address', 'reply_to_address']) ? 'email' : 'text' }}"
                               value="{{ old($field, $setting?->$field ?? ($field === 'display_name' ? $brand->imprint_name : '')) }}"
                               @if (!in_array($field, ['display_name', 'reply_to_address'])) :required="enabled" @endif
                               class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                        <x-input-error :messages="$errors->get($field)" class="mt-2" />
                    </div>
                @endforeach
                <div>
                    <label for="smtp_port" class="mb-2 block text-sm font-medium">SMTP Port</label>
                    <input id="smtp_port" name="smtp_port" type="number" min="1" max="65535" :required="enabled"
                           value="{{ old('smtp_port', $setting?->smtp_port ?? 465) }}"
                           class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                    <x-input-error :messages="$errors->get('smtp_port')" class="mt-2" />
                </div>
                <div>
                    <label for="smtp_encryption" class="mb-2 block text-sm font-medium">SMTP Encryption</label>
                    <select id="smtp_encryption" name="smtp_encryption" class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                        <option value="ssl" @selected(old('smtp_encryption', $setting?->smtp_encryption ?? 'ssl') === 'ssl')>SSL (usually port 465)</option>
                        <option value="tls" @selected(old('smtp_encryption', $setting?->smtp_encryption ?? 'ssl') === 'tls')>STARTTLS (usually port 587)</option>
                    </select>
                </div>
                <div>
                    <label for="password" class="mb-2 block text-sm font-medium">SMTP Password</label>
                    <input id="password" name="password" type="password" autocomplete="new-password"
                           :required="enabled && @js(!$setting?->encrypted_password)"
                           placeholder="{{ $setting?->encrypted_password ? 'Leave blank to keep the saved password' : 'Mailbox password' }}"
                           class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>
            </div>
            <p class="text-sm text-slate-500">Use an existing mailbox or sender authorized by your email provider. Passwords are stored encrypted. This setting does not enable the CRM inbox.</p>
            <button type="submit" class="rounded-xl bg-emerald-800 px-5 py-3 font-semibold text-white">Save Sender Settings</button>
        </form>
    </div>
</x-app-layout>
