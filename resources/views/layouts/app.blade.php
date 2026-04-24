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
                <div class="flex min-h-14 sm:min-h-16 md:min-h-[72px] items-center justify-between gap-2 sm:gap-3 md:gap-4 px-3 py-2 sm:px-4 sm:py-3 md:px-6 lg:px-8">
                    <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                        <button @click="sidebarOpen = true" class="inline-flex h-9 w-9 sm:h-10 sm:w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-600 transition hover:bg-slate-100 md:hidden shrink-0">
                            <i class="bi bi-list text-base sm:text-lg leading-none" aria-hidden="true"></i>
                        </button>

                        <a href="{{ $dashboardHref }}" class="flex items-center gap-2 sm:gap-3 min-w-0">
                            <div class="inline-flex h-9 w-9 sm:h-10 sm:w-10 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-md shadow-indigo-300/60 ring-1 ring-indigo-500/30 shrink-0">
                                <x-application-logo class="h-3.5 w-3.5 sm:h-4 sm:w-4 fill-current" />
                            </div>
                            <div class="min-w-0">
                                <div class="text-sm sm:text-base md:text-lg lg:text-xl font-semibold leading-4 sm:leading-5 text-slate-900 truncate">SELG Ballot Scanner</div>
                                <div class="hidden sm:block text-xs text-slate-500 truncate">Election Management System</div>
                            </div>
                        </a>
                    </div>

                    <div class="flex items-center gap-2 sm:gap-3">
                        <div class="hidden text-right sm:block">
                            <div class="text-xs sm:text-sm font-semibold text-slate-900 truncate">{{ $currentUser?->name }}</div>
                            <div class="text-xs text-slate-500 truncate">{{ $userRole }}</div>
                        </div>

                        <a href="{{ route('profile.edit') }}" class="inline-flex items-center gap-1.5 sm:gap-2 rounded-lg sm:rounded-xl border border-slate-200 bg-white px-2 sm:px-3 py-1.5 sm:py-2 text-xs sm:text-sm font-medium text-slate-700 transition hover:bg-slate-50 shrink-0">
                            <i class="bi bi-person-fill text-sm leading-none" aria-hidden="true"></i>
                            <span class="hidden sm:inline">Profile</span>
                        </a>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-1.5 sm:gap-2 rounded-lg sm:rounded-xl border border-slate-200 bg-white px-2 sm:px-3 py-1.5 sm:py-2 text-xs sm:text-sm font-semibold text-slate-700 transition hover:bg-slate-50 shrink-0">
                                <i class="bi bi-box-arrow-right text-sm leading-none" aria-hidden="true"></i>
                                <span class="hidden sm:inline">Logout</span>
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
