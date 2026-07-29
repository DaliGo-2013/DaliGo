<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'DaliGo') }}</title>

        {{-- PWA (spike P-SPK-01): instalable + tema. iOS ignora los icons del
             manifest, por eso el apple-touch-icon aparte. --}}
        <link rel="manifest" href="/manifest.json">
        <meta name="theme-color" content="#EA580C">
        <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-full font-sans text-neutral-900 antialiased bg-neutral-50">
        {{-- Shell V4 (menú Talana): sidebar izquierda + columna de contenido.
             `menuAbierto` controla el drawer móvil (bajo lg:). min-w-0 en la
             columna es OBLIGATORIO: sin él una tabla ancha revienta el flex y
             aparece scroll horizontal (gate R-31). --}}
        <div class="flex min-h-screen" x-data="{ menuAbierto: false }" @keydown.escape.window="menuAbierto = false">
            <x-layout.sidebar />

            {{-- Tocar fuera cierra el drawer (solo móvil). --}}
            <div x-show="menuAbierto" x-cloak @click="menuAbierto = false"
                 class="fixed inset-0 z-30 bg-neutral-900/30 lg:hidden" aria-hidden="true"></div>

            <div class="flex min-w-0 flex-1 flex-col">
                <x-layout.topbar />

                {{-- Ancho de pagina: UNA variable para los TRES contenedores de
                     abajo (titulo, aviso y cuerpo). Antes cada vista escribia su
                     propio `mx-auto max-w-*` mientras la banda del titulo estaba
                     fija en max-w-7xl aca: 47 de 69 pantallas tenian el titulo
                     desalineado del contenido, hasta 352px. Saliendo los tres de
                     $maxW, desalinearlos deja de ser posible.
                     Los literales van AQUI y no en AppLayout.php porque Tailwind
                     v4 solo escanea resources/** (en app/ se purgarian). Los
                     tokens validos los valida AppLayout::ANCHOS. --}}
                @php $maxW = ['listado' => 'max-w-7xl', 'formulario' => 'max-w-3xl'][$ancho]; @endphp

                @isset($header)
                    <header class="border-b border-neutral-200 bg-white">
                        <div class="mx-auto {{ $maxW }} px-4 py-6 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <main class="flex-1">
                    {{-- Mini-notificacion de bloqueo (403/404), canal propio
                         session('aviso'). Va aca, UNA sola vez y como primer
                         nodo de <main>, para que cubra TODAS las vistas (el
                         dashboard incluido, que no pinta <x-status-alert>) y se
                         vea sin scroll en movil. --}}
                    <x-aviso :aviso="session('aviso')" :maxW="$maxW" />

                    {{-- El contenedor centrado del cuerpo vive ACA, no en cada
                         vista: es lo que garantiza que el contenido comparta
                         borde con el titulo. Las vistas solo traen su envoltorio
                         de padding vertical. --}}
                    <div class="mx-auto {{ $maxW }} px-4 sm:px-6 lg:px-8">
                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>

        {{-- Muleta táctil para los listados largos: va ACÁ, una sola vez, y así
             sirve en las 69 pantallas sin que ninguna vista tenga que acordarse.
             Solo se muestra en móvil y recién pasados 600px de scroll. --}}
        <x-volver-arriba />
    </body>
</html>
