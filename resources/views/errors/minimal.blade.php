<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'VisionFlow CRM')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
    <main class="flex min-h-screen items-center justify-center px-6 py-12">
        <section class="w-full max-w-xl rounded-3xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-50 text-2xl font-black text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-200">
                @yield('code', '!')
            </div>
            <h1 class="mt-6 text-3xl font-bold">@yield('heading', 'Something went wrong')</h1>
            <p class="mt-3 text-sm leading-6 text-slate-500 dark:text-zinc-400">
                @yield('message', 'The page could not be loaded right now. Please try again in a moment.')
            </p>
            <a href="{{ url('/') }}" class="mt-7 inline-flex rounded-xl bg-emerald-700 px-5 py-3 text-sm font-bold text-white shadow-sm hover:bg-emerald-800">
                Back to Dashboard
            </a>
        </section>
    </main>
</body>
</html>
