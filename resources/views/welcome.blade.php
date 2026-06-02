<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DaliGo</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-slate-800 text-slate-100 antialiased">
    <div class="flex min-h-screen flex-col items-center justify-center px-6 py-12">
        <main class="w-full max-w-2xl">
            <div class="mb-10 text-center">
                <div class="mb-4 inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-indigo-500/10 ring-1 ring-indigo-400/30">
                    <span class="text-3xl font-black tracking-tight text-indigo-300">D</span>
                </div>
                <h1 class="text-4xl font-bold tracking-tight">DaliGo</h1>
                <p class="mt-2 text-sm text-slate-400">Despliegue verificado &middot; Laravel 12 + MySQL 5.7</p>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div class="rounded-xl border border-slate-700/60 bg-slate-800/40 p-4">
                    <p class="text-xs uppercase tracking-wide text-slate-400">Entorno</p>
                    <p class="mt-1 text-lg font-semibold">{{ $appEnv }}</p>
                </div>
                <div class="rounded-xl border border-slate-700/60 bg-slate-800/40 p-4">
                    <p class="text-xs uppercase tracking-wide text-slate-400">PHP</p>
                    <p class="mt-1 text-lg font-semibold">{{ $phpVersion }}</p>
                </div>
                <div class="rounded-xl border border-slate-700/60 bg-slate-800/40 p-4">
                    <p class="text-xs uppercase tracking-wide text-slate-400">Laravel</p>
                    <p class="mt-1 text-lg font-semibold">{{ $laravelVersion }}</p>
                </div>
                <div class="rounded-xl border p-4 {{ $db['ok'] ? 'border-emerald-500/40 bg-emerald-500/5' : 'border-amber-500/40 bg-amber-500/5' }}">
                    <p class="text-xs uppercase tracking-wide text-slate-400">Base de datos ({{ $db['name'] }})</p>
                    <p class="mt-1 text-lg font-semibold {{ $db['ok'] ? 'text-emerald-300' : 'text-amber-300' }}">
                        {{ $db['ok'] ? 'Conectada' : 'Sin conexion' }}
                    </p>
                    @if (! empty($db['detail']))
                        <p class="mt-1 truncate text-xs text-slate-500" title="{{ $db['detail'] }}">{{ $db['detail'] }}</p>
                    @endif
                </div>
            </div>

            <p class="mt-10 text-center text-xs text-slate-500">
                {{ now()->format('Y-m-d H:i') }} &middot; {{ request()->getHost() }}
            </p>
        </main>
    </div>
</body>
</html>
