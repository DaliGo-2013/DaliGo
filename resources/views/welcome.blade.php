<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DaliGo</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full font-sans text-slate-100 antialiased bg-gradient-to-br from-slate-950 via-slate-900 to-slate-800">
    <div class="flex min-h-screen flex-col">
        <header class="mx-auto flex w-full max-w-5xl items-center justify-between px-6 py-6">
            <div class="flex items-center gap-2">
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-500/10 font-black text-indigo-300 ring-1 ring-indigo-400/30">D</span>
                <span class="text-lg font-semibold tracking-tight">DaliGo</span>
            </div>
            <nav class="flex items-center gap-2 text-sm">
                @auth
                    <a href="{{ url('/dashboard') }}" class="rounded-lg bg-indigo-500 px-4 py-2 font-semibold text-white transition duration-150 hover:bg-indigo-400 active:scale-[0.98]">{{ __('Dashboard') }}</a>
                @else
                    <a href="{{ route('login') }}" class="px-3 py-2 text-slate-300 transition duration-150 hover:text-white">{{ __('Log in') }}</a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="rounded-lg bg-indigo-500 px-4 py-2 font-semibold text-white transition duration-150 hover:bg-indigo-400 active:scale-[0.98]">{{ __('Register') }}</a>
                    @endif
                @endauth
            </nav>
        </header>

        <main class="flex flex-1 items-center justify-center px-6">
            <div class="dg-enter max-w-2xl text-center">
                <span class="inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-indigo-500/10 text-3xl font-black text-indigo-300 ring-1 ring-indigo-400/30">D</span>
                <h1 class="mt-6 text-4xl font-bold tracking-tight sm:text-5xl">DaliGo</h1>
                <p class="mt-4 text-lg text-slate-400">Plataforma DaliGo. Inicia sesión o crea tu cuenta para comenzar.</p>

                <div class="mt-8 flex items-center justify-center gap-4">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="rounded-lg bg-indigo-500 px-6 py-3 font-semibold text-white shadow transition duration-150 hover:bg-indigo-400 active:scale-[0.98]">Ir al panel</a>
                    @else
                        <a href="{{ route('login') }}" class="rounded-lg bg-indigo-500 px-6 py-3 font-semibold text-white shadow transition duration-150 hover:bg-indigo-400 active:scale-[0.98]">{{ __('Log in') }}</a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="rounded-lg border border-slate-700 px-6 py-3 font-semibold text-slate-200 transition duration-150 hover:bg-slate-800 active:scale-[0.98]">{{ __('Register') }}</a>
                        @endif
                    @endauth
                </div>
            </div>
        </main>

        <footer class="mx-auto w-full max-w-5xl px-6 py-6 text-center text-xs text-slate-600">
            DaliGo &middot; {{ now()->year }}
        </footer>
    </div>
</body>
</html>
