<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">

        <title>System Maintenance | {{ config('app.name', 'CreatiVision CRM') }}</title>
        <link rel="icon" type="image/png" href="{{ asset('images/CreativeVision LOGO-navsite.png') }}">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-50 font-sans text-slate-950 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <main class="flex min-h-screen items-center justify-center px-6 py-12">
            <section class="w-full max-w-2xl rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                @if ($isPreview ?? false)
                    <div class="mb-6 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-left text-sm text-sky-800 dark:border-sky-400/30 dark:bg-sky-400/10 dark:text-sky-100">
                        Preview only. Maintenance mode is not enabled from this page.
                    </div>
                @endif

                <div class="mx-auto flex h-24 w-24 items-center justify-center rounded-full bg-emerald-50 ring-1 ring-emerald-100 dark:bg-emerald-400/10 dark:ring-emerald-400/20">
                    <img src="{{ asset('images/CreativeVision LOGO-navsite.png') }}"
                         alt="CreatiVision Outsourcing"
                         class="h-16 w-16 object-contain">
                </div>

                <p class="mt-8 text-sm font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">
                    Scheduled Maintenance
                </p>

                <h1 class="mt-3 text-3xl font-bold tracking-normal text-slate-950 dark:text-white">
                    We are improving the CRM
                </h1>

                <p class="mx-auto mt-4 max-w-xl text-base leading-7 text-slate-600 dark:text-zinc-300">
                    The system is temporarily unavailable while we perform updates. Please check back shortly.
                </p>

                @if ($estimatedReturn)
                    <div class="mx-auto mt-6 max-w-md rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-100">
                        Estimated return: {{ $estimatedReturn }}
                    </div>
                @endif

                <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                    @auth
                        <a href="{{ route('dashboard') }}"
                           class="inline-flex items-center justify-center rounded-xl bg-emerald-700 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2 dark:bg-emerald-400 dark:text-zinc-950 dark:hover:bg-emerald-300">
                            Try Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}"
                           class="inline-flex items-center justify-center rounded-xl bg-emerald-700 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2 dark:bg-emerald-400 dark:text-zinc-950 dark:hover:bg-emerald-300">
                            Back to Login
                        </a>
                    @endauth
                </div>
            </section>
        </main>
    </body>
</html>
