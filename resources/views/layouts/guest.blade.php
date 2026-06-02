<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'DaliGo') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-full font-sans text-neutral-900 antialiased bg-neutral-50">
        <div class="min-h-screen flex flex-col justify-center items-center px-6 py-12">
            <a href="/" class="group flex flex-col items-center gap-3">
                <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-600 text-2xl font-black text-white shadow-sm transition duration-200 group-hover:bg-brand-700">D</span>
                <span class="text-lg font-semibold tracking-tight text-neutral-900">DaliGo</span>
            </a>

            <div class="dg-enter mt-8 w-full sm:max-w-md rounded-2xl border border-neutral-200 bg-white px-6 py-8 shadow-sm sm:px-8">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
