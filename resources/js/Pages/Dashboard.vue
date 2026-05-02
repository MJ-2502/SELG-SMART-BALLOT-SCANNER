<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);

// Shape per election (from DashboardController):
// { id, election_name, election_date, status, total_ballots, scanned_ballots }
// status is one of: 'pending' | 'active' | 'completed'
const elections = computed(() => page.props.assignedElections ?? []);

// ── Bottom sheet ─────────────────────────────────────────────────────
const selectedElection = ref(null);
const sheetOpen = ref(false);

const openSheet = (election) => {
    selectedElection.value = election;
    sheetOpen.value = true;
    document.body.style.overflow = 'hidden';
};

const closeSheet = () => {
    sheetOpen.value = false;
    document.body.style.overflow = '';
    setTimeout(() => { selectedElection.value = null; }, 300);
};

// ── Status config — matches Election model: pending | active | completed ──
const statusConfig = {
    active:    { label: 'Active',    dot: 'bg-emerald-500 animate-pulse', badge: 'bg-emerald-100 text-emerald-700 border-emerald-200' },
    pending:   { label: 'Pending',   dot: 'bg-amber-400',                 badge: 'bg-amber-100 text-amber-700 border-amber-200' },
    completed: { label: 'Completed', dot: 'bg-slate-400',                 badge: 'bg-slate-100 text-slate-500 border-slate-200' },
};

const getStatus = (status) =>
    statusConfig[status?.toLowerCase()] ?? {
        label: status ?? 'Unknown',
        dot: 'bg-slate-400',
        badge: 'bg-slate-100 text-slate-500 border-slate-200',
    };

// election_date is a single datetime — format it nicely
const formatDate = (iso) => {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('en-PH', {
        weekday: 'short', month: 'long', day: 'numeric', year: 'numeric',
    });
};

const formatDateShort = (iso) => {
    if (!iso) return null;
    return new Date(iso).toLocaleDateString('en-PH', {
        month: 'short', day: 'numeric', year: 'numeric',
    });
};

const formatTime = (iso) => {
    if (!iso) return null;
    return new Date(iso).toLocaleTimeString('en-PH', {
        hour: 'numeric', minute: '2-digit', hour12: true,
    });
};

const scannerUrl = (election) =>
    election?.scanner_url ?? `/scanner?election_id=${election?.id}&autostart=1`;

const scanProgress = (election) => {
    if (!election?.total_ballots || election.total_ballots === 0) return null;
    return Math.round(((election.scanned_ballots ?? 0) / election.total_ballots) * 100);
};

const isActive    = (e) => e?.status === 'active';
const isPending   = (e) => e?.status === 'pending';
const isCompleted = (e) => e?.status === 'completed';
</script>

<template>
    <Head title="Dashboard" />

    <div class="ui-page max-w-lg mx-auto px-4 pt-4 pb-12">

        <!-- ── Greeting ── -->
        <div class="mb-6">
            <h1 class="text-xl font-bold tracking-tight text-slate-900">
                Hello, {{ user?.name?.split(' ')[0] ?? 'Facilitator' }} 👋
            </h1>
            <p class="mt-1 text-sm text-slate-500">
                {{ elections.length
                    ? 'Tap an election card to view details and start scanning.'
                    : 'No elections have been assigned to you yet.' }}
            </p>
        </div>

        <!-- ── Empty state ── -->
        <div v-if="!elections.length"
            class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-14 text-center">
            <i class="bi bi-calendar3 mx-auto text-slate-300 mb-3 text-5xl leading-none" aria-hidden="true"></i>
            <p class="text-sm font-semibold text-slate-600">No elections assigned</p>
            <p class="mt-1 text-xs text-slate-400">
                Your adviser will assign you to an election. Check back later.
            </p>
        </div>

        <!-- ── Election cards ── -->
        <div v-else class="space-y-3">
            <button
                v-for="election in elections"
                :key="election.id"
                type="button"
                class="w-full text-left rounded-2xl border bg-white shadow-sm overflow-hidden active:scale-[0.985] transition-transform duration-100"
                :class="isActive(election) ? 'border-indigo-200' : 'border-slate-200'"
                @click="openSheet(election)">

                <!-- Active top stripe -->
                <div v-if="isActive(election)"
                    class="h-1 bg-gradient-to-r from-indigo-500 to-violet-500"></div>

                <div class="p-4">
                    <!-- Election name + status pill -->
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-slate-900 leading-snug">
                                {{ election.election_name }}
                            </p>
                            <p v-if="election.election_date" class="mt-0.5 text-xs text-slate-500">
                                {{ formatDateShort(election.election_date) }}
                                <span v-if="formatTime(election.election_date)" class="text-slate-400">
                                    · {{ formatTime(election.election_date) }}
                                </span>
                            </p>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-full border"
                                :class="getStatus(election.status).badge">
                                {{ getStatus(election.status).label }}
                            </span>
                            <i class="bi bi-chevron-right text-slate-400 flex-shrink-0 text-base leading-none" aria-hidden="true"></i>
                        </div>
                    </div>

                    <!-- Progress bar (compact, only if data available) -->
                    <div v-if="scanProgress(election) !== null" class="mt-3">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs text-slate-400">
                                {{ election.scanned_ballots ?? 0 }} / {{ election.total_ballots }} ballots scanned
                            </span>
                            <span class="text-xs font-bold"
                                :class="scanProgress(election) === 100 ? 'text-emerald-600' : 'text-indigo-600'">
                                {{ scanProgress(election) }}%
                            </span>
                        </div>
                        <div class="h-1.5 w-full rounded-full bg-slate-100 overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-500"
                                :class="scanProgress(election) === 100 ? 'bg-emerald-500' : 'bg-indigo-500'"
                                :style="{ width: `${scanProgress(election)}%` }">
                            </div>
                        </div>
                    </div>

                    <!-- Active: show quick scan hint -->
                    <div v-if="isActive(election)" class="mt-3 flex items-center gap-1.5 text-xs text-indigo-600 font-medium">
                        <i class="bi bi-upc-scan text-indigo-600 text-sm leading-none" aria-hidden="true"></i>
                        Tap to view details and start scanning
                    </div>
                </div>
            </button>
        </div>

        <p class="mt-8 text-center text-xs text-slate-400">
            Contact your adviser if you have concerns about your assignment.
        </p>
    </div>

    <!-- ════════════════════════════════════════════════════
         ELECTION DETAIL BOTTOM SHEET
         ════════════════════════════════════════════════════ -->
    <Teleport to="body">
        <div v-if="selectedElection"
            class="fixed inset-0 z-[80] flex flex-col justify-end"
            @click.self="closeSheet">

            <!-- Backdrop -->
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity duration-300"
                :class="sheetOpen ? 'opacity-100' : 'opacity-0'"
                @click="closeSheet">
            </div>

            <!-- Sheet panel -->
            <div class="relative w-full max-w-lg mx-auto bg-white rounded-t-3xl shadow-2xl flex flex-col max-h-[88dvh] transition-transform duration-300"
                :class="sheetOpen ? 'translate-y-0' : 'translate-y-full'">

                <!-- Drag handle -->
                <div class="flex justify-center pt-3 pb-1 flex-shrink-0">
                    <div class="h-1 w-10 rounded-full bg-slate-300"></div>
                </div>

                <!-- Scrollable body -->
                <div class="overflow-y-auto overscroll-contain flex-1 min-h-0 px-5 pb-2">

                    <!-- Header -->
                    <div class="flex items-start justify-between gap-3 pt-3 pb-4 border-b border-slate-100">
                        <div class="min-w-0">
                            <!-- Status dot + label -->
                            <div class="flex items-center gap-2 mb-1.5">
                                <span class="inline-block h-2 w-2 rounded-full flex-shrink-0"
                                    :class="getStatus(selectedElection.status).dot"></span>
                                <span class="text-xs font-bold uppercase tracking-wide"
                                    :class="{
                                        'text-emerald-700': isActive(selectedElection),
                                        'text-amber-700':   isPending(selectedElection),
                                        'text-slate-500':   isCompleted(selectedElection),
                                    }">
                                    {{ getStatus(selectedElection.status).label }}
                                </span>
                            </div>
                            <h2 class="text-lg font-bold text-slate-900 leading-snug">
                                {{ selectedElection.election_name }}
                            </h2>
                        </div>
                        <button type="button"
                            class="flex-shrink-0 rounded-xl p-2 bg-slate-100 text-slate-500 active:bg-slate-200"
                            @click="closeSheet">
                            <i class="bi bi-x-lg text-slate-500 text-xl leading-none" aria-hidden="true"></i>
                        </button>
                    </div>

                    <!-- Details -->
                    <div class="py-4 space-y-3">

                        <!-- Election date -->
                        <div class="flex items-start gap-3 rounded-xl bg-slate-50 px-4 py-3">
                            <i class="bi bi-calendar3 text-slate-400 text-xl flex-shrink-0 mt-0.5 leading-none" aria-hidden="true"></i>
                            <div>
                                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Election Date</p>
                                <p class="text-sm font-medium text-slate-800 mt-0.5">
                                    {{ formatDate(selectedElection.election_date) }}
                                </p>
                                <p v-if="formatTime(selectedElection.election_date)" class="text-xs text-slate-500">
                                    {{ formatTime(selectedElection.election_date) }}
                                </p>
                            </div>
                        </div>

                        <!-- Ballot stats — 3-column grid -->
                        <div v-if="selectedElection.total_ballots != null || selectedElection.scanned_ballots != null"
                            class="grid grid-cols-3 gap-2">
                            <div class="rounded-xl bg-slate-50 px-3 py-3 text-center">
                                <p class="text-2xl font-bold text-slate-900">
                                    {{ selectedElection.total_ballots ?? '—' }}
                                </p>
                                <p class="text-xs text-slate-400 mt-0.5">Total</p>
                            </div>
                            <div class="rounded-xl bg-emerald-50 px-3 py-3 text-center">
                                <p class="text-2xl font-bold text-emerald-700">
                                    {{ selectedElection.scanned_ballots ?? 0 }}
                                </p>
                                <p class="text-xs text-emerald-600 mt-0.5">Scanned</p>
                            </div>
                            <div class="rounded-xl bg-amber-50 px-3 py-3 text-center">
                                <p class="text-2xl font-bold text-amber-700">
                                    {{ (selectedElection.total_ballots ?? 0) - (selectedElection.scanned_ballots ?? 0) }}
                                </p>
                                <p class="text-xs text-amber-600 mt-0.5">Remaining</p>
                            </div>
                        </div>

                        <!-- Progress bar (detailed) -->
                        <div v-if="scanProgress(selectedElection) !== null"
                            class="rounded-xl bg-slate-50 px-4 py-3">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">
                                    Scan Progress
                                </p>
                                <span class="text-sm font-bold"
                                    :class="scanProgress(selectedElection) === 100 ? 'text-emerald-600' : 'text-indigo-600'">
                                    {{ scanProgress(selectedElection) }}%
                                </span>
                            </div>
                            <div class="h-3 w-full rounded-full bg-slate-200 overflow-hidden">
                                <div class="h-full rounded-full transition-all duration-700"
                                    :class="scanProgress(selectedElection) === 100 ? 'bg-emerald-500' : 'bg-indigo-500'"
                                    :style="{ width: `${scanProgress(selectedElection)}%` }">
                                </div>
                            </div>
                            <p v-if="scanProgress(selectedElection) === 100"
                                class="mt-2 text-xs text-emerald-600 font-semibold text-center">
                                ✓ All ballots have been scanned
                            </p>
                        </div>

                        <!-- Status-specific notice -->
                        <div v-if="isPending(selectedElection)"
                            class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                            <i class="bi bi-clock text-amber-800 text-xl flex-shrink-0 mt-0.5 leading-none" aria-hidden="true"></i>
                            <span>This election hasn't started yet. Scanning will be available once your adviser activates it.</span>
                        </div>

                        <div v-if="isCompleted(selectedElection)"
                            class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-500">
                            <i class="bi bi-lock text-slate-500 text-xl flex-shrink-0 mt-0.5 leading-none" aria-hidden="true"></i>
                            <span>This election is completed. Scanning is no longer available.</span>
                        </div>
                    </div>
                </div>

                <!-- ── Fixed action bar ── -->
                <div class="flex-shrink-0 px-5 pt-3 border-t border-slate-100"
                    style="padding-bottom: max(env(safe-area-inset-bottom, 0px), 1.25rem);">

                    <!-- Active → Start Scanning -->
                    <Link v-if="isActive(selectedElection)"
                        :href="scannerUrl(selectedElection)"
                        class="flex w-full items-center justify-center gap-2.5 rounded-2xl bg-indigo-600 py-4 text-base font-bold text-white active:bg-indigo-700 shadow-sm">
                        <i class="bi bi-upc-scan text-white text-xl leading-none" aria-hidden="true"></i>
                        Start Scanning
                    </Link>

                    <!-- Pending → locked -->
                    <div v-else-if="isPending(selectedElection)"
                        class="flex w-full items-center justify-center gap-2 rounded-2xl bg-amber-50 border border-amber-200 py-4 text-sm font-semibold text-amber-600 cursor-not-allowed select-none">
                        <i class="bi bi-clock text-amber-600 text-xl leading-none" aria-hidden="true"></i>
                        Waiting for adviser to start
                    </div>

                    <!-- Completed → locked -->
                    <div v-else
                        class="flex w-full items-center justify-center gap-2 rounded-2xl bg-slate-100 py-4 text-sm font-semibold text-slate-400 cursor-not-allowed select-none">
                        <i class="bi bi-lock text-slate-400 text-xl leading-none" aria-hidden="true"></i>
                        Election completed
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>