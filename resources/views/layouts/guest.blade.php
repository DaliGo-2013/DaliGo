<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'DaliGo') }}</title>

        {{-- PWA (spike P-SPK-01): mismo head que app.blade (el login vive dentro del scope de la app instalada). --}}
        <link rel="manifest" href="/manifest.json">
        <meta name="theme-color" content="#EA580C">
        <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-full font-sans text-neutral-900 antialiased bg-neutral-50">
        {{-- MARCO HORIZONTAL — se declara ACÁ y en ningún otro lado (ver «Marco
             horizontal» en las Reglas de diseño de CLAUDE.md).

             Medido a 375px antes de esto: el campo de un formulario del QR tenía
             217px de ancho útil sobre 375, o sea el 42% de la pantalla se iba en
             padding, sumado por 5 capas (12+12+16+24+24). En el celular el aire
             de escritorio no es elegancia: es texto que se lee en una columna
             angosta y teclado que tapa la mitad.

             px-3 en móvil (12px) y el px-6 de siempre desde sm:. --}}
        <div class="min-h-screen flex flex-col justify-center items-center px-3 py-12 sm:px-6">
            <a href="/" class="group flex flex-col items-center gap-3">
                <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-600 text-2xl font-black text-white shadow-sm transition duration-200 group-hover:bg-brand-700">D</span>
                <span class="text-lg font-semibold tracking-tight text-neutral-900">DaliGo</span>
            </a>

            <div class="dg-enter mt-8 w-full sm:max-w-md rounded-2xl border border-neutral-200 bg-white px-4 py-6 shadow-sm sm:px-8 sm:py-8">
                {{-- Mismo canal que el layout autenticado: sin esto, un back()->with('aviso')
             que aterrice en una pantalla de invitado se perderia en silencio (el bug
             que este lote arreglo para session('status') en el dashboard). --}}
        <x-aviso :aviso="session('aviso')" />

        {{ $slot }}
            </div>
        </div>
    </body>
</html>
