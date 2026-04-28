<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
</script>

<template>
    <Head title="Dashboard" />

    <div class="ui-page">
        <div class="ui-card max-w-3xl">
            <div class="mb-5">
                <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-slate-900">Dashboard</h1>
                <p class="mt-2 text-sm text-slate-600">
                    Welcome back{{ user?.name ? `, ${user.name}` : '' }}. You can continue scanning ballots from here.
                </p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 sm:p-5">
                <h2 class="text-base font-semibold text-slate-900">Quick Actions</h2>
                <p class="mt-1 text-sm text-slate-600">Use the scanner to capture or upload ballots, then review and submit detected votes.</p>
                <div class="mt-4 flex flex-wrap gap-3">
                    <Link
                        href="/scanner"
                        class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700"
                    >
                        Open Scanner
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
