<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-gradient-to-b from-slate-50 to-white" x-data="{ sidebarOpen: false }">
        @php
            $currentUser = auth()->user();
            $userRole = $currentUser?->isAdviser() ? 'Adviser' : 'Facilitator';
            $dashboardHref = $currentUser?->isAdviser() ? route('admin.dashboard') : route('dashboard');
        @endphp

        <div class="min-h-screen">
            @include('layouts.navigation')

            <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur">
                <div class="flex min-h-[72px] items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
                    <div class="flex items-center gap-3">
                        <button @click="sidebarOpen = true" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-600 transition hover:bg-slate-100 md:hidden">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>

                        <a href="{{ $dashboardHref }}" class="flex items-center gap-3">
                            <div class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-md shadow-indigo-300/60 ring-1 ring-indigo-500/30">
                                <x-application-logo class="h-4 w-4 fill-current" />
                            </div>
                            <div>
                                <div class="text-xl font-semibold leading-5 text-slate-900">SELG Ballot Scanner</div>
                                <div class="text-xs text-slate-500">Election Management System</div>
                            </div>
                        </a>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="hidden text-right sm:block">
                            <div class="text-sm font-semibold text-slate-900">{{ $currentUser?->name }}</div>
                            <div class="text-xs text-slate-500">{{ $userRole }}</div>
                        </div>

                        <a href="{{ route('profile.edit') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 3.75a4.5 4.5 0 1 1 0 9 4.5 4.5 0 0 1 0-9Zm-7.5 15a7.5 7.5 0 1 1 15 0v1.5h-15v-1.5Z" /></svg>
                            <span class="hidden sm:inline">Profile</span>
                        </a>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M15.75 3.75h-7.5A2.25 2.25 0 0 0 6 6v12a2.25 2.25 0 0 0 2.25 2.25h7.5V18h-7.5V6h7.5V3.75Zm-1.06 4.72L13.63 9.53l1.97 1.97H9v1.5h6.6l-1.97 1.97 1.06 1.06 3.75-3.75-3.75-3.75Z" /></svg>
                                <span>Logout</span>
                            </button>
                        </form>
                    </div>
                </div>
            </header>

            <div class="md:pl-[17.5rem]">

                @isset($header)
                    <header class="border-b border-slate-200 bg-white/70 backdrop-blur">
                        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <!-- Page Content -->
                <main class="py-8">
                    @hasSection('content')
                        @yield('content')
                    @else
                        {{ $slot ?? '' }}
                    @endif
                </main>
            </div>
        </div>
    </body>
</html>
