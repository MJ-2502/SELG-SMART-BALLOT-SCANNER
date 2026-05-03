<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const sidebarOpen = ref(false);
const sidebarCollapsed = ref(false);

onMounted(() => {
    try {
        sidebarCollapsed.value = JSON.parse(localStorage.getItem('sidebar-collapsed') ?? 'false');
    } catch (error) {
        sidebarCollapsed.value = false;
    }
});

watch(sidebarCollapsed, (value) => {
    localStorage.setItem('sidebar-collapsed', JSON.stringify(value));
});

const currentUser = computed(() => page.props.auth?.user ?? null);
const isAdviser = computed(() => Boolean(currentUser.value?.is_adviser));
const canUseScanner = computed(() => currentUser.value?.role === 'facilitator');
const dashboardHref = computed(() => (isAdviser.value ? '/admin' : '/dashboard'));

const navItems = computed(() => {
    const items = [
        {
            label: 'Dashboard',
            href: dashboardHref.value,
            active: page.component === 'Admin/Dashboard' || page.url === '/dashboard' || page.url === '/admin',
            icon: 'bi bi-graph-up',
        },
    ];

    if (canUseScanner.value) {
        items.push({
            label: 'Ballot Scanner',
            href: '/scanner',
            active: page.url.startsWith('/scanner'),
            icon: 'bi bi-upc-scan',
        });
    }

    if (isAdviser.value) {
        items.push(
            { label: 'Election Management', href: '/elections', active: page.url.startsWith('/elections'), icon: 'bi bi-calendar-check' },
            { label: 'Positions', href: '/positions', active: page.url.startsWith('/positions'), icon: 'bi bi-list-task' },
            { label: 'Candidates', href: '/candidates', active: page.url.startsWith('/candidates'), icon: 'bi bi-person-badge' },
            { label: 'Ballot Management', href: '/admin/ballot-management', active: page.url.startsWith('/admin/ballot-management'), icon: 'bi bi-ui-checks-grid' },
            { label: 'Facilitators', href: '/facilitators', active: page.url.startsWith('/facilitators'), icon: 'bi bi-people' },
            { label: 'Reports', href: '/admin/reports', active: page.component.startsWith('Admin/Reports/'), icon: 'bi bi-file-earmark-bar-graph' },
        );
    }

    return items;
});

function toggleSidebar() {
    sidebarCollapsed.value = !sidebarCollapsed.value;
}
</script>

<template>
    <div class="min-h-screen">
        <div v-cloak v-show="sidebarOpen" class="fixed inset-0 z-40 md:hidden">
            <div class="absolute inset-0 bg-slate-950/35" @click="sidebarOpen = false"></div>
            <aside class="absolute left-0 top-0 flex h-full w-72 flex-col bg-slate-50 text-slate-800 shadow-2xl">
                <div class="flex h-16 items-center justify-between border-b border-slate-200 px-5">
                    <div class="flex items-center gap-3">
                        <div class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-600 text-white">
                                <svg viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="h-4 w-4">
                                    <path d="M1.5 1a.5.5 0 0 0-.5.5v3a.5.5 0 0 1-1 0v-3A1.5 1.5 0 0 1 1.5 0h3a.5.5 0 0 1 0 1zM11 .5a.5.5 0 0 1 .5-.5h3A1.5 1.5 0 0 1 16 1.5v3a.5.5 0 0 1-1 0v-3a.5.5 0 0 0-.5-.5h-3a.5.5 0 0 1-.5-.5M.5 11a.5.5 0 0 1 .5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 1 0 1h-3A1.5 1.5 0 0 1 0 14.5v-3a.5.5 0 0 1 .5-.5m15 0a.5.5 0 0 1 .5.5v3a1.5 1.5 0 0 1-1.5 1.5h-3a.5.5 0 0 1 0-1h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 1 .5-.5M3 4.5a.5.5 0 0 1 1 0v7a.5.5 0 0 1-1 0zm2 0a.5.5 0 0 1 1 0v7a.5.5 0 0 1-1 0zm2 0a.5.5 0 0 1 1 0v7a.5.5 0 0 1-1 0zm2 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v7a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3 0a.5.5 0 0 1 1 0v7a.5.5 0 0 1-1 0" />
                                </svg>
                        </div>
                        <div>
                            <div class="text-sm font-semibold leading-4 text-slate-900">SELG Ballot Scanner</div>
                            <div class="text-xs text-slate-500">Navigation</div>
                        </div>
                    </div>
                    <button class="rounded-md p-2 text-slate-500 hover:bg-slate-200 hover:text-slate-800" @click="sidebarOpen = false">
                        <i class="bi bi-x-lg text-base leading-none" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto px-4 py-5">
                    <div class="rounded-2xl border border-slate-200 bg-white p-2 shadow-sm">
                        <Link
                            v-for="item in navItems"
                            :key="item.label"
                            :href="item.href"
                            class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium"
                            :class="item.active ? 'bg-indigo-100 text-indigo-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
                        >
                            <i :class="[item.icon, item.active ? 'text-indigo-600' : 'text-slate-400']" class="text-base leading-none shrink-0" aria-hidden="true"></i>
                            <span>{{ item.label }}</span>
                        </Link>
                    </div>
                </div>
            </aside>
        </div>

        <aside class="fixed bottom-6 left-4 top-[5.75rem] z-10 hidden flex-col rounded-2xl border border-slate-200 bg-white/92 text-slate-800 shadow-lg shadow-slate-200/80 backdrop-blur ease-out md:flex" :class="sidebarCollapsed ? 'w-20' : 'w-64'">
            <div class="flex items-center border-b border-slate-200/80" :class="sidebarCollapsed ? 'justify-center px-2 py-2' : 'justify-between px-3 py-2.5'">
                <div v-show="!sidebarCollapsed" class="flex items-center gap-2 min-w-0">
                    <span class="text-xs font-semibold tracking-wide text-slate-600 truncate">Navigation</span>
                </div>

                <button
                    type="button"
                    @click="toggleSidebar()"
                    class="sidebar-toggle-btn inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 text-slate-600 ease-out hover:bg-slate-100 hover:border-indigo-300 hover:text-indigo-700"
                    :class="sidebarCollapsed ? 'bg-indigo-600 text-white border-indigo-600 shadow-lg shadow-indigo-200/80' : ''"
                    aria-label="Toggle sidebar"
                    title="Toggle sidebar"
                >
                    <i class="bi bi-layout-sidebar text-base" :class="sidebarCollapsed ? 'rotate-180 scale-105' : ''" aria-hidden="true"></i>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto py-3" :class="sidebarCollapsed ? 'px-2' : 'px-3'">
                <Link
                    v-for="item in navItems"
                    :key="item.label"
                    :href="item.href"
                    :title="item.label"
                    class="flex items-center rounded-xl text-sm font-medium overflow-hidden"
                    :class="[item.active ? 'bg-indigo-100 text-indigo-700 shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900', sidebarCollapsed ? 'justify-center px-2 py-3' : 'gap-3 px-4 py-3']"
                >
                    <i
                        :class="[item.icon, item.active ? 'text-indigo-600' : 'text-slate-400']"
                        class="text-base leading-none shrink-0"
                        aria-hidden="true"
                    ></i>
                    <span
                        class="whitespace-nowrap"
                        :class="sidebarCollapsed ? 'max-w-0 opacity-0 translate-x-2' : 'max-w-48 opacity-100 translate-x-0'"
                    >
                        {{ item.label }}
                    </span>
                </Link>
            </div>
        </aside>

        <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur">
            <div class="flex min-h-14 sm:min-h-16 md:min-h-[72px] items-center justify-between gap-2 sm:gap-3 md:gap-4 px-3 py-2 sm:px-4 sm:py-3 md:px-6 lg:px-8">
                <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                    <button @click="sidebarOpen = true" class="inline-flex h-9 w-9 sm:h-10 sm:w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-600 transition hover:bg-slate-100 md:hidden shrink-0">
                        <i class="bi bi-list text-base sm:text-lg leading-none" aria-hidden="true"></i>
                    </button>

                    <Link :href="dashboardHref" class="flex items-center gap-2 sm:gap-3 min-w-0">
                        <div class="inline-flex h-9 w-9 sm:h-10 sm:w-10 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-md shadow-indigo-300/60 ring-1 ring-indigo-500/30 shrink-0">
                            <svg viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="h-3.5 w-3.5 sm:h-4 sm:w-4">
                                <path d="M1.5 1a.5.5 0 0 0-.5.5v3a.5.5 0 0 1-1 0v-3A1.5 1.5 0 0 1 1.5 0h3a.5.5 0 0 1 0 1zM11 .5a.5.5 0 0 1 .5-.5h3A1.5 1.5 0 0 1 16 1.5v3a.5.5 0 0 1-1 0v-3a.5.5 0 0 0-.5-.5h-3a.5.5 0 0 1-.5-.5M.5 11a.5.5 0 0 1 .5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 1 0 1h-3A1.5 1.5 0 0 1 0 14.5v-3a.5.5 0 0 1 .5-.5m15 0a.5.5 0 0 1 .5.5v3a1.5 1.5 0 0 1-1.5 1.5h-3a.5.5 0 0 1 0-1h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 1 .5-.5M3 4.5a.5.5 0 0 1 1 0v7a.5.5 0 0 1-1 0zm2 0a.5.5 0 0 1 1 0v7a.5.5 0 0 1-1 0zm2 0a.5.5 0 0 1 1 0v7a.5.5 0 0 1-1 0zm2 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v7a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3 0a.5.5 0 0 1 1 0v7a.5.5 0 0 1-1 0" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm sm:text-base md:text-lg lg:text-xl font-semibold leading-4 sm:leading-5 text-slate-900 truncate">SELG Ballot Scanner</div>
                            <div class="hidden sm:block text-xs text-slate-500 truncate">Election Management System</div>
                        </div>
                    </Link>
                </div>

                <div class="flex items-center gap-2 sm:gap-3">
                    <div class="hidden text-right sm:block">
                        <div class="text-xs sm:text-sm font-semibold text-slate-900 truncate">{{ currentUser?.name }}</div>
                        <div class="text-xs text-slate-500 truncate">{{ currentUser?.role === 'adviser' ? 'Adviser' : 'Facilitator' }}</div>
                    </div>

                    <Link v-if="isAdviser" href="/profile" class="inline-flex items-center gap-1.5 sm:gap-2 rounded-lg sm:rounded-xl border border-slate-200 bg-white px-2 sm:px-3 py-1.5 sm:py-2 text-xs sm:text-sm font-medium text-slate-700 transition hover:bg-slate-50 shrink-0">
                        <i class="bi bi-person-fill text-sm leading-none" aria-hidden="true"></i>
                        <span class="hidden sm:inline">Profile</span>
                    </Link>

                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        type="button"
                        class="inline-flex items-center gap-1.5 sm:gap-2 rounded-lg sm:rounded-xl border border-slate-200 bg-white px-2 sm:px-3 py-1.5 sm:py-2 text-xs sm:text-sm font-semibold text-slate-700 transition hover:bg-slate-50 shrink-0"
                    >
                        <i class="bi bi-box-arrow-right text-sm leading-none" aria-hidden="true"></i>
                        <span class="hidden sm:inline">Logout</span>
                    </Link>
                </div>
            </div>
        </header>

        <div class="transition-[padding] duration-300 ease-out" :class="sidebarCollapsed ? 'md:pl-24' : 'md:pl-[17.5rem]'">
            <main class="py-8">
                <slot />
            </main>
        </div>
    </div>
</template>