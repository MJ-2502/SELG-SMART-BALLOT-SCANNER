<template>
    <div class="relative min-h-screen overflow-hidden bg-gradient-to-br from-indigo-50 via-slate-50 to-blue-50 flex flex-col sm:justify-center items-center px-4 py-8">
        <div class="pointer-events-none absolute -left-24 -top-20 h-72 w-72 rounded-full bg-indigo-300/25 blur-3xl"></div>
        <div class="pointer-events-none absolute -right-20 -bottom-24 h-72 w-72 rounded-full bg-sky-300/20 blur-3xl"></div>

        <div class="relative w-full sm:max-w-md">
            <div class="flex flex-col items-center text-center">
                <a href="/" class="inline-flex items-center justify-center">
                    <img :src="logoUrl" alt="SELG Ballot Scanner" class="h-20 w-20 object-contain" />
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

<script setup>
const logoUrl = '/images/logo.png';
</script>