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
        <header class="mx-auto flex w-full max-w-5xl items-center px-6 py-6">
            <div class="flex items-center gap-2">
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-brand-600 font-black text-white">D</span>
                <span class="text-lg font-semibold tracking-tight">DaliGo</span>
            </div>
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

                {{-- El ingreso a servicio técnico por QR NO se anuncia en la portada
                     (reduce exposición pública). Se llega SOLO escaneando el QR físico
                     del mostrador (link firmado): ver la página admin "Códigos QR" y la
                     ruta pública `ingreso-taller.*`. --}}
            </div>
        </main>

        <footer class="mx-auto w-full max-w-5xl px-6 py-6 text-center text-xs text-neutral-400">
            DaliGo &middot; {{ now()->year }}
        </footer>
    </div>
</body>
</html>
