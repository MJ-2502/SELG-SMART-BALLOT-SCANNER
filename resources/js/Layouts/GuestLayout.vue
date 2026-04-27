<template>
    <div class="relative min-h-screen overflow-hidden bg-gradient-to-br from-indigo-50 via-slate-50 to-blue-50 flex flex-col sm:justify-center items-center px-4 py-8">
        <div class="pointer-events-none absolute -left-24 -top-20 h-72 w-72 rounded-full bg-indigo-300/25 blur-3xl"></div>
        <div class="pointer-events-none absolute -right-20 -bottom-24 h-72 w-72 rounded-full bg-sky-300/20 blur-3xl"></div>

        <div class="relative w-full sm:max-w-md">
            <div class="flex flex-col items-center text-center">
                <a href="/" class="inline-flex items-center justify-center rounded-2xl border border-indigo-100/80 bg-white/70 p-4 shadow-lg shadow-indigo-200/40 backdrop-blur">
                    <svg viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="h-14 w-14 text-indigo-600">
                        <path d="M1.5 1a.5.5 0 0 0-.5.5v3a.5.5 0 0 1-1 0v-3A1.5 1.5 0 0 1 1.5 0h3a.5.5 0 0 1 0 1zM11 .5a.5.5 0 0 1 .5-.5h3A1.5 1.5 0 0 1 16 1.5v3a.5.5 0 0 1-1 0v-3a.5.5 0 0 0-.5-.5h-3a.5.5 0 0 1-.5-.5M.5 11a.5.5 0 0 1 .5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 1 0 1h-3A1.5 1.5 0 0 1 0 14.5v-3a.5.5 0 0 1 .5-.5m15 0a.5.5 0 0 1 .5.5v3a1.5 1.5 0 0 1-1.5 1.5h-3a.5.5 0 0 1 0-1h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 1 .5-.5M3 4.5a.5.5 0 0 1 1 0v7a.5.5 0 0 1-1 0zm2 0a.5.5 0 0 1 1 0v7a.5.5 0 0 1-1 0zm2 0a.5.5 0 0 1 1 0v7a.5.5 0 0 1-1 0zm2 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v7a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3 0a.5.5 0 0 1 1 0v7a.5.5 0 0 1-1 0" />
                    </svg>
                </a>
                <div class="mt-3">
                    <div class="text-base font-semibold tracking-tight text-slate-900">SELG Ballot Scanner</div>
                    <div class="text-xs text-slate-500">Election Management System</div>
                </div>
            </div>
        </div>

        <div class="relative w-full sm:max-w-md mt-6 px-6 py-6 bg-white/88 border border-indigo-100 shadow-xl shadow-indigo-200/35 overflow-hidden sm:rounded-2xl backdrop-blur">
            <slot />
        </div>
    </div>
</template>