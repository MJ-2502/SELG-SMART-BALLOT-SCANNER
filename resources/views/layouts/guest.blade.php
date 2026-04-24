<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'SELG Ballot Scanner') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="relative min-h-screen overflow-hidden bg-gradient-to-br from-indigo-50 via-slate-50 to-blue-50 flex flex-col sm:justify-center items-center px-4 py-8">
            <div class="pointer-events-none absolute -left-24 -top-20 h-72 w-72 rounded-full bg-indigo-300/25 blur-3xl"></div>
            <div class="pointer-events-none absolute -right-20 -bottom-24 h-72 w-72 rounded-full bg-sky-300/20 blur-3xl"></div>

            <div class="relative w-full sm:max-w-md">
                <div class="flex flex-col items-center text-center">
                    <a href="/" class="inline-flex items-center justify-center rounded-2xl border border-indigo-100/80 bg-white/70 p-4 shadow-lg shadow-indigo-200/40 backdrop-blur">
                        <x-application-logo class="w-14 h-14 fill-current text-indigo-600" />
                    </a>
                    <div class="mt-3">
                        <div class="text-base font-semibold tracking-tight text-slate-900">SELG Ballot Scanner</div>
                        <div class="text-xs text-slate-500">Election Management System</div>
                    </div>
                </div>
            </div>

            <div class="relative w-full sm:max-w-md mt-6 px-6 py-6 bg-white/88 border border-indigo-100 shadow-xl shadow-indigo-200/35 overflow-hidden sm:rounded-2xl backdrop-blur">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
