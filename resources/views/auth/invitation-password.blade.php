@php
    $brandName = 'CreatiVision Outsourcing';
    $crmName = 'CreatiVision CRM';
    $brandPrimary = '#065f46';
    $brandAccent = '#d1fae5';
    $brandLogo = asset('images/CreativeVision-LOGO-1.png');
@endphp

<x-guest-layout plain>
    <div class="invitation-page flex min-h-screen bg-slate-50"
         style="--brand-primary: {{ $brandPrimary }}; --brand-accent: {{ $brandAccent }};">
        <div class="invitation-panel hidden min-h-screen w-1/2 min-w-[420px] flex-col justify-between p-12 text-white lg:flex xl:p-16">
            <div>
                <div class="max-w-xl">
                    <img src="{{ $brandLogo }}"
                         alt="{{ $brandName }}"
                         class="h-32 w-auto max-w-full object-contain">
                </div>

                <h1 class="mt-12 max-w-lg text-4xl font-bold leading-tight xl:text-5xl">
                    Activate your CRM account.
                </h1>

                <p class="mt-5 max-w-md text-lg leading-8 text-zinc-300">
                    Create your password to access your workspace, assigned role, and team workflow.
                </p>
            </div>

            <div class="grid max-w-lg grid-cols-2 gap-4">
                <div class="rounded-2xl border border-white/15 bg-white/10 p-5 backdrop-blur">
                    <p class="text-sm text-zinc-300">Account</p>
                    <h3 class="mt-2 text-2xl font-bold leading-tight text-white">Secure Setup</h3>
                </div>

                <div class="rounded-2xl border border-white/15 bg-white/10 p-5 backdrop-blur">
                    <p class="text-sm text-zinc-300">Access</p>
                    <h3 class="mt-2 text-2xl font-bold leading-tight text-white">Role-Based</h3>
                </div>
            </div>
        </div>

        <div class="flex w-full items-center justify-center px-6 py-12 lg:w-1/2 lg:px-12">
            <div class="w-full max-w-md">
                <div class="mb-8">
                    <p class="invitation-accent mb-3 text-sm font-semibold uppercase tracking-wide">
                        Invitation Accepted
                    </p>
                    <h2 class="text-3xl font-bold text-slate-900">Create your password</h2>
                    <p class="mt-2 text-slate-500">
                        Welcome, {{ $user->first_name }}. Set your password to activate your {{ $crmName }} account.
                    </p>
                </div>

                <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Account Email</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ $user->email }}</p>
                </div>

                <form method="POST" action="{{ $storeUrl }}" class="space-y-5">
                    @csrf

                    <div>
                        <label for="password" class="mb-2 block text-sm font-medium text-slate-700">
                            Password <span class="text-rose-600">*</span>
                        </label>
                        <input id="password"
                               name="password"
                               type="password"
                               required
                               autocomplete="new-password"
                               class="invitation-input w-full rounded-xl border-slate-300 px-4 py-3 shadow-sm">
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>

                    <div>
                        <label for="password_confirmation" class="mb-2 block text-sm font-medium text-slate-700">
                            Confirm Password <span class="text-rose-600">*</span>
                        </label>
                        <input id="password_confirmation"
                               name="password_confirmation"
                               type="password"
                               required
                               autocomplete="new-password"
                               class="invitation-input w-full rounded-xl border-slate-300 px-4 py-3 shadow-sm">
                        <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                    </div>

                    <button type="submit"
                            class="invitation-button w-full rounded-xl px-5 py-3 font-semibold text-white shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2">
                        Activate Account
                    </button>
                </form>

                <p class="mt-6 text-center text-sm text-slate-500">
                    This invitation link is time-limited for account security.
                </p>
            </div>
        </div>
    </div>

    <style>
        .invitation-panel {
            background: radial-gradient(circle at top left, #0f5f45 0%, #063b2d 30%, #021c16 62%, #050505 100%);
        }

        .invitation-accent {
            color: var(--brand-primary);
        }

        .invitation-input:focus {
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 1px var(--brand-primary);
            outline: none;
        }

        .invitation-button {
            background: linear-gradient(135deg, #065f46 0%, #022c22 100%);
        }

        .invitation-button:hover {
            filter: brightness(.9);
        }

        .invitation-button:focus {
            --tw-ring-color: var(--brand-primary);
        }
    </style>
</x-guest-layout>
