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
    <body class="min-h-full font-sans text-slate-100 antialiased bg-gradient-to-br from-slate-950 via-slate-900 to-slate-800">
        <div class="min-h-screen flex flex-col justify-center items-center px-6 py-12">
            <a href="/" class="group flex flex-col items-center gap-3">
                <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-500/10 text-2xl font-black text-indigo-300 ring-1 ring-indigo-400/30 transition duration-200 group-hover:ring-indigo-400/60">D</span>
                <span class="text-lg font-semibold tracking-tight text-slate-200">DaliGo</span>
            </a>

            <div class="dg-enter mt-8 w-full sm:max-w-md rounded-2xl border border-slate-700/60 bg-slate-900/60 px-6 py-8 shadow-2xl backdrop-blur sm:px-8">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
