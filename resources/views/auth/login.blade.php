<x-guest-layout plain>
    <div class="flex min-h-screen bg-slate-50">
        <div class="hidden min-h-screen w-1/2 min-w-[420px] flex-col justify-between p-10 text-white lg:flex xl:p-14"
             style="background: radial-gradient(circle at top left, #0f4f32 0%, #082d21 42%, #050706 100%);">
            <div>
                <div class="max-w-xl">
                    <img src="{{ asset('images/CreativeVision LOGO 1.png') }}"
                         alt="CreatiVision Outsourcing"
                         class="h-24 w-auto max-w-full object-contain">

                    <h1 class="mt-14 max-w-lg text-4xl font-bold leading-tight text-white drop-shadow-[0_2px_2px_rgba(0,0,0,0.5)] xl:text-[3.25rem]">
                        Welcome to VisionFlow CRM.
                    </h1>
                    <p class="mt-5 max-w-md text-lg leading-8 text-emerald-50/85 drop-shadow-[0_1px_1px_rgba(0,0,0,0.35)]">
                        VisionFlow is a CRM built for CreatiVision Outsourcing to manage the full operation of leads, projects, and sales.
                    </p>
                </div>
            </div>

            <div class="grid max-w-lg grid-cols-2 gap-4">
                <div class="rounded-2xl border border-emerald-100/15 bg-white/10 p-5 shadow-sm backdrop-blur">
                    <p class="text-sm text-emerald-50/70">Functions</p>
                    <h3 class="mt-2 text-2xl font-bold leading-tight text-emerald-100">Multiple Operations</h3>
                </div>

                <div class="rounded-2xl border border-emerald-100/15 bg-white/10 p-5 shadow-sm backdrop-blur">
                    <p class="text-sm text-emerald-50/70">Accounts</p>
                    <h3 class="mt-2 text-2xl font-bold leading-tight text-emerald-100">Multi-Brand CRM</h3>
                </div>
            </div>
        </div>

        <div class="flex w-full items-center justify-center px-6 py-12 lg:w-1/2 lg:px-12">
            <div class="w-full max-w-md">
                <div class="mb-8">
                    <h2 class="text-3xl font-bold text-slate-900">Welcome back</h2>
                    <p class="mt-2 text-slate-500">
                        Login to continue to the VisionFlow CRM dashboard.
                    </p>
                </div>

                <x-auth-session-status class="mb-4" :status="session('status')" />

                <form method="POST" action="{{ route('login') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">
                            Email Address
                        </label>

                        <input id="email"
                               type="email"
                               name="email"
                               value="{{ old('email') }}"
                               required
                               autofocus
                               autocomplete="username"
                               class="w-full rounded-xl border-slate-300 px-4 py-3 shadow-sm focus:border-emerald-700 focus:ring-emerald-700">

                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <div x-data="{ showPassword: false }">
                        <label class="mb-2 block text-sm font-medium text-slate-700">
                            Password
                        </label>

                        <div class="relative">
                            <input id="password"
                                   x-bind:type="showPassword ? 'text' : 'password'"
                                   name="password"
                                   required
                                   autocomplete="current-password"
                                   class="w-full rounded-xl border-slate-300 px-4 py-3 pr-12 shadow-sm focus:border-emerald-700 focus:ring-emerald-700">

                            <button type="button"
                                    x-on:click="showPassword = ! showPassword"
                                    x-bind:aria-label="showPassword ? 'Hide password' : 'Show password'"
                                    class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-slate-500 hover:text-emerald-800">
                                <svg x-show="! showPassword" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>

                                <svg x-show="showPassword" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.58 10.58a2 2 0 0 0 2.84 2.84" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.88 5.5A8.8 8.8 0 0 1 12 5.25c6 0 9.75 6.75 9.75 6.75a16.6 16.6 0 0 1-3.08 3.64" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.61 6.61C3.84 8.47 2.25 12 2.25 12S6 18.75 12 18.75c1.38 0 2.65-.36 3.79-.91" />
                                </svg>
                            </button>
                        </div>

                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <label class="flex items-center">
                            <input type="checkbox"
                                   name="remember"
                                   class="rounded border-slate-300 text-emerald-700 shadow-sm focus:ring-emerald-700">
                            <span class="ml-2 text-sm text-slate-600">Remember me</span>
                        </label>

                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}"
                               class="text-sm font-medium text-emerald-700 hover:text-emerald-800">
                                Forgot password?
                            </a>
                        @endif
                    </div>

                    <button type="submit"
                            class="w-full rounded-xl bg-emerald-950 px-5 py-3 font-semibold text-emerald-50 shadow-sm ring-1 ring-emerald-200/20 hover:bg-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">
                        Login
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-guest-layout>
