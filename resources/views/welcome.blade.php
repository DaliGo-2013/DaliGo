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
<body class="min-h-full font-sans text-neutral-900 antialiased bg-neutral-50">
    <div class="flex min-h-screen flex-col">
        <header class="mx-auto flex w-full max-w-5xl items-center justify-between px-6 py-6">
            <div class="flex items-center gap-2">
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-brand-600 font-black text-white">D</span>
                <span class="text-lg font-semibold tracking-tight">DaliGo</span>
            </div>
            <nav class="flex items-center gap-2 text-sm">
                @auth
                    <a href="{{ url('/dashboard') }}" class="rounded-lg bg-brand-600 px-4 py-2 font-semibold text-white transition duration-150 hover:bg-brand-700 active:scale-[0.98]">{{ __('Dashboard') }}</a>
                @else
                    <a href="{{ route('login') }}" class="rounded-lg bg-brand-600 px-4 py-2 font-semibold text-white transition duration-150 hover:bg-brand-700 active:scale-[0.98]">{{ __('Log in') }}</a>
                @endauth
            </nav>
        </header>

        <main class="flex flex-1 items-center justify-center px-6">
            <div class="dg-enter max-w-2xl text-center">
                <span class="inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-brand-600 text-3xl font-black text-white shadow-sm">D</span>
                <h1 class="mt-6 text-4xl font-bold tracking-tight text-neutral-900 sm:text-5xl">DaliGo</h1>
                <p class="mt-4 text-lg text-neutral-600">Plataforma DaliGo. Inicia sesión o crea tu cuenta para comenzar.</p>

                <div class="mt-8 flex items-center justify-center gap-4">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="rounded-lg bg-brand-600 px-6 py-3 font-semibold text-white shadow-sm transition duration-150 hover:bg-brand-700 active:scale-[0.98]">Ir al panel</a>
                    @else
                        <a href="{{ route('login') }}" class="rounded-lg bg-brand-600 px-6 py-3 font-semibold text-white shadow-sm transition duration-150 hover:bg-brand-700 active:scale-[0.98]">{{ __('Log in') }}</a>
                    @endauth
                </div>

                {{-- Ingreso a servicio técnico SIN cuenta (P-M12-01): pregunta →
                     sucursal → QR de esa sucursal. Los QR se dibujan en el cliente
                     (canvas[data-qr] → app.js). --}}
                @if (($sucursalesTaller ?? collect())->isNotEmpty())
                    <div class="mx-auto mt-10 w-full max-w-md text-left"
                         x-data="{ paso: 'inicio', sucursal: null }">

                        {{-- Paso 1: la pregunta --}}
                        <button type="button" x-show="paso === 'inicio'" @click="paso = 'sucursal'"
                            class="group flex w-full items-center justify-between gap-4 rounded-2xl border border-neutral-200 bg-white px-6 py-5 text-left shadow-sm transition duration-150 hover:border-brand-300 hover:shadow active:scale-[0.99]">
                            <span>
                                <span class="block font-semibold text-neutral-900">¿Vas a ingresar un producto a servicio técnico?</span>
                                <span class="mt-0.5 block text-sm text-neutral-500">Toca aquí para empezar — no necesitas cuenta.</span>
                            </span>
                            <span class="text-xl text-brand-600 transition group-hover:translate-x-0.5">&rarr;</span>
                        </button>

                        {{-- Paso 2: elegir sucursal --}}
                        <div x-show="paso === 'sucursal'" x-cloak
                             class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm">
                            <p class="font-semibold text-neutral-900">¿En qué sucursal estás?</p>
                            <p class="mt-0.5 text-sm text-neutral-500">Elige dónde vas a dejar tu equipo.</p>
                            <div class="mt-4 grid grid-cols-1 gap-2">
                                @foreach ($sucursalesTaller as $s)
                                    <button type="button" @click="sucursal = '{{ $s->id }}'; paso = 'qr'"
                                        class="flex items-center justify-between rounded-xl border border-neutral-200 px-4 py-3 text-left font-medium text-neutral-900 transition duration-150 hover:border-brand-300 hover:bg-brand-50 active:scale-[0.99]">
                                        <span>{{ $s->nombre }}</span>
                                        <span class="text-neutral-300">&rarr;</span>
                                    </button>
                                @endforeach
                            </div>
                            <button type="button" @click="paso = 'inicio'"
                                class="mt-4 text-sm text-neutral-400 underline hover:text-neutral-600">Volver</button>
                        </div>

                        {{-- Paso 3: el QR de la sucursal elegida --}}
                        <div x-show="paso === 'qr'" x-cloak
                             class="rounded-2xl border border-neutral-200 bg-white p-6 text-center shadow-sm">
                            @foreach ($sucursalesTaller as $s)
                                @php $urlIngreso = \Illuminate\Support\Facades\URL::signedRoute('ingreso-taller.create', ['sucursal' => $s->id]); @endphp
                                <div x-show="sucursal === '{{ $s->id }}'" x-cloak>
                                    <p class="font-semibold text-neutral-900">Servicio Técnico &middot; {{ $s->nombre }}</p>
                                    <p class="mt-1 text-sm text-neutral-500">Escanea este código con la cámara de tu celular para ingresar tu equipo.</p>
                                    <div class="mt-4 inline-block rounded-xl border border-neutral-200 p-3">
                                        <canvas data-qr="{{ $urlIngreso }}" width="220" height="220" class="h-52 w-52"></canvas>
                                    </div>
                                    <p class="mt-3 text-sm">
                                        <a href="{{ $urlIngreso }}" class="font-medium text-brand-600 hover:text-brand-700">o continúa aquí en este dispositivo &rarr;</a>
                                    </p>
                                </div>
                            @endforeach
                            <button type="button" @click="paso = 'sucursal'"
                                class="mt-5 text-sm text-neutral-400 underline hover:text-neutral-600">&larr; Cambiar sucursal</button>
                        </div>
                    </div>
                @endif
            </div>
        </main>

        <footer class="mx-auto w-full max-w-5xl px-6 py-6 text-center text-xs text-neutral-400">
            DaliGo &middot; {{ now()->year }}
        </footer>
    </div>
</body>
</html>
