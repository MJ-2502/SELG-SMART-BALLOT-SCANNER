<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { Chart, registerables } from 'chart.js';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

Chart.register(...registerables);

const props = defineProps({
    hasElection: Boolean,
    selectedElection: Object,
    stats: Object,
    tallyData: Object,
    availableElections: Array,
});

const showElectionModal = ref(false);

// --- Computed helpers ---
const positionTallies = computed(() => props.tallyData?.position_tallies ?? []);

const totalScanned = computed(() => props.tallyData?.summary?.total_scanned ?? props.stats?.ballots_scanned ?? 0);

const validPct = computed(() => {
    const total = totalScanned.value;
    if (!total) return 0;
    return Math.round(((props.tallyData?.summary?.valid_submissions ?? props.stats?.valid_ballots ?? 0) / total) * 100);
});

const invalidPct = computed(() => {
    const total = totalScanned.value;
    if (!total) return 0;
    return Math.round(((props.tallyData?.summary?.flagged_submissions ?? props.stats?.invalid_ballots ?? 0) / total) * 100);
});

const topCandidateMaxVotes = computed(() => {
    const candidates = props.tallyData?.top_candidates ?? [];
    if (!candidates.length) return 1;
    return Math.max(1, ...candidates.map((c) => Number(c.votes ?? 0)));
});

const electionStatus = computed(() => {
    const s = String(props.selectedElection?.status ?? '').toLowerCase();
    if (s === 'active') return { label: 'Active', classes: 'bg-emerald-50 text-emerald-700 ring-emerald-200', dotClass: 'bg-emerald-500 animate-pulse' };
    if (s === 'completed') return { label: 'Completed', classes: 'bg-slate-100 text-slate-600 ring-slate-200', dotClass: 'bg-slate-400' };
    return { label: 'Pending', classes: 'bg-amber-50 text-amber-700 ring-amber-200', dotClass: 'bg-amber-400' };
});

const submissionInsight = computed(() => {
    const pct = validPct.value;
    if (pct >= 95) return 'Excellent — nearly all ballots are valid.';
    if (pct >= 85) return 'Good — low invalid rate, scanning is performing well.';
    if (pct >= 70) return 'Moderate — consider checking scanner calibration.';
    return 'High invalid rate — scanner inspection recommended.';
});

// --- Date formatting ---
const formatDate = (iso) => {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('en-PH', {
        weekday: 'short', month: 'long', day: 'numeric', year: 'numeric',
    });
};

const formatTime = (iso) => {
    if (!iso) return null;
    return new Date(iso).toLocaleTimeString('en-PH', {
        hour: 'numeric', minute: '2-digit', hour12: true,
    });
};

// --- Responsive chart grid ---
const fallbackChartColors = [
    '#2563EB', '#059669', '#F97316', '#7C3AED', '#DC2626', '#0891B2', '#CA8A04', '#DB2777',
];

const chartCanvasByPosition = ref({});
const chartInstances = new Map();
const positionGridRef = ref(null);
const gridColCount = ref(2);
let resizeObserver = null;

const updateGridCols = (width) => {
    if (width < 560) gridColCount.value = 1;
    else if (width < 960) gridColCount.value = 2;
    else gridColCount.value = 3;
};

const gridClass = computed(() => ({
    'grid-cols-1': gridColCount.value === 1,
    'grid-cols-2': gridColCount.value === 2,
    'grid-cols-3': gridColCount.value === 3,
}));

const setPositionChartCanvas = (positionId, element) => {
    if (element) { chartCanvasByPosition.value[positionId] = element; return; }
    delete chartCanvasByPosition.value[positionId];
};

const chartColorForCandidate = (candidate, index) => {
    const c = String(candidate?.color_code ?? '').trim().toUpperCase();
    return /^#[0-9A-F]{6}$/.test(c) ? c : fallbackChartColors[index % fallbackChartColors.length];
};

const destroyPositionCharts = () => { chartInstances.forEach((i) => i.destroy()); chartInstances.clear(); };

const renderPositionCharts = () => {
    destroyPositionCharts();
    positionTallies.value.forEach((position) => {
        const canvas = chartCanvasByPosition.value[position.position_id];
        if (!canvas || !position.candidates?.length) return;
        const instance = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: position.candidates.map((c) => c.name),
                datasets: [{
                    label: 'Votes',
                    data: position.candidates.map((c) => Number(c.votes ?? 0)),
                    backgroundColor: position.candidates.map((c, i) => chartColorForCandidate(c, i)),
                    borderRadius: 8,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { ticks: { display: false } },
                    y: { beginAtZero: true, ticks: { precision: 0, stepSize: 1 } },
                },
            },
        });
        chartInstances.set(position.position_id, instance);
    });
};

const resizeAllCharts = () => { nextTick(() => { chartInstances.forEach((i) => i.resize()); }); };

onMounted(async () => {
    await nextTick();
    renderPositionCharts();
    if (positionGridRef.value) {
        updateGridCols(positionGridRef.value.offsetWidth);
        resizeObserver = new ResizeObserver((entries) => {
            for (const entry of entries) { updateGridCols(entry.contentRect.width); resizeAllCharts(); }
        });
        resizeObserver.observe(positionGridRef.value);
    }
});

watch(positionTallies, async () => { await nextTick(); renderPositionCharts(); }, { deep: true });
onBeforeUnmount(() => { destroyPositionCharts(); resizeObserver?.disconnect(); });
</script>

<template>
    <Head title="Election Dashboard" />

    <div class="ui-page">

        <!-- No election state -->
        <div v-if="!hasElection" class="ui-card relative overflow-hidden">
            <div class="pointer-events-none absolute -right-16 -top-16 h-40 w-40 rounded-full bg-indigo-300/30 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-20 left-10 h-44 w-44 rounded-full bg-sky-300/30 blur-3xl"></div>
            <div class="relative max-w-3xl">
                <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-slate-900 mb-2">Welcome to SELG Ballot Scanner</h1>
                <p class="text-slate-600 mb-8">No election has been created yet. Start by creating your first election to unlock live monitoring and progress insights.</p>
                <div class="rounded-2xl border border-indigo-100 bg-white/90 p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-900 mb-2">Ready to begin?</h2>
                    <p class="text-sm text-slate-600 mb-5">Set up election details first, then ballot generation and scanner workflows will follow automatically.</p>
                    <Link href="/elections/create" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
                        Start / Create Election
                    </Link>
                </div>
            </div>
        </div>

        <div v-else class="ui-card">

            <!-- ── Page header ──────────────────────────────────────── -->
            <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-slate-900">{{ selectedElection?.election_name }}</h1>
                    <!-- Election name + live status badge -->
                    <div class="flex flex-wrap items-center gap-2 mt-1.5">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset"
                            :class="electionStatus.classes"
                        >
                            <span class="h-1.5 w-1.5 rounded-full" :class="electionStatus.dotClass"></span>
                            {{ electionStatus.label }}
                        </span>
                        <span class="text-sm text-slate-500">{{ formatDate(selectedElection?.election_date) }}</span>
                        <span v-if="formatTime(selectedElection?.election_date)" class="text-xs text-slate-400">
                            · {{ formatTime(selectedElection?.election_date) }}
                        </span>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        @click="showElectionModal = true"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors"
                    >
                        <i class="bi bi-arrow-left-right text-slate-400 text-base leading-none" aria-hidden="true"></i>
                        Change Election
                    </button>
                    <Link
                        href="/admin/reports"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        <i class="bi bi-file-earmark-bar-graph text-slate-400 text-base leading-none" aria-hidden="true"></i>
                        Reports
                    </Link>
                </div>
            </div>

            <!-- ── Compact Stats Strip ─────────────────────────────── -->
            <div class="rounded-xl border border-slate-200 bg-white mb-6 overflow-hidden">

                <!-- Top row: 3 neutral stats -->
                <div class="grid grid-cols-3 divide-x divide-slate-100 border-b border-slate-100">
                    <!-- Total Positions -->
                    <div class="flex items-center gap-3 px-5 py-3.5">
                        <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-slate-100">
                            <i class="bi bi-list-task text-slate-500 text-base leading-none" aria-hidden="true"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs text-slate-400 leading-none mb-0.5">Positions</p>
                            <p class="text-xl font-bold text-slate-900 tabular-nums leading-tight">{{ stats.total_positions ?? 0 }}</p>
                        </div>
                    </div>

                    <!-- Total Candidates -->
                    <div class="flex items-center gap-3 px-5 py-3.5">
                        <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-slate-100">
                            <i class="bi bi-people text-slate-500 text-base leading-none" aria-hidden="true"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs text-slate-400 leading-none mb-0.5">Candidates</p>
                            <p class="text-xl font-bold text-slate-900 tabular-nums leading-tight">{{ stats.total_candidates ?? 0 }}</p>
                        </div>
                    </div>

                    <!-- Ballots Scanned -->
                    <div class="flex items-center gap-3 px-5 py-3.5">
                        <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-slate-100">
                            <i class="bi bi-upc-scan text-slate-500 text-base leading-none" aria-hidden="true"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs text-slate-400 leading-none mb-0.5">Ballots Scanned</p>
                            <p class="text-xl font-bold text-slate-900 tabular-nums leading-tight">{{ (stats.ballots_scanned ?? 0).toLocaleString() }}</p>
                        </div>
                    </div>
                </div>

                <!-- Bottom row: 3 colored KPI stats + inline mini progress bar for ballot quality -->
                <div class="grid grid-cols-3 divide-x divide-slate-100 bg-slate-50/40">

                    <!-- Valid Ballots — green -->
                    <div class="px-5 py-3.5">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="flex items-center gap-1.5 text-xs font-medium text-emerald-700">
                                <i class="bi bi-check-circle-fill text-emerald-600 text-sm leading-none" aria-hidden="true"></i>
                                Valid Ballots
                            </span>
                            <span class="text-xs font-semibold text-emerald-600 tabular-nums">{{ validPct }}%</span>
                        </div>
                        <p class="text-2xl font-bold text-emerald-800 tabular-nums leading-none mb-2">{{ (stats.valid_ballots ?? 0).toLocaleString() }}</p>
                        <div class="h-1.5 rounded-full bg-emerald-100 overflow-hidden">
                            <div class="h-full rounded-full bg-emerald-500 transition-all duration-500" :style="{ width: `${Math.max(totalScanned ? 2 : 0, validPct)}%` }"></div>
                        </div>
                    </div>

                    <!-- Invalid Ballots — rose -->
                    <div class="px-5 py-3.5">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="flex items-center gap-1.5 text-xs font-medium text-rose-700">
                                <i class="bi bi-exclamation-circle text-rose-600 text-sm leading-none" aria-hidden="true"></i>
                                Invalid Ballots
                            </span>
                            <span class="text-xs font-semibold text-rose-600 tabular-nums">{{ invalidPct }}%</span>
                        </div>
                        <p class="text-2xl font-bold text-rose-800 tabular-nums leading-none mb-2">{{ (stats.invalid_ballots ?? 0).toLocaleString() }}</p>
                        <div class="h-1.5 rounded-full bg-rose-100 overflow-hidden">
                            <div class="h-full rounded-full bg-rose-500 transition-all duration-500" :style="{ width: `${Math.max(totalScanned && invalidPct > 0 ? 2 : 0, invalidPct)}%` }"></div>
                        </div>
                    </div>

                    <!-- Voter Turnout — amber -->
                    <div class="px-5 py-3.5">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="flex items-center gap-1.5 text-xs font-medium text-amber-700">
                                <i class="bi bi-people-fill text-amber-600 text-sm leading-none" aria-hidden="true"></i>
                                Voter Turnout
                            </span>
                            <span class="text-xs text-amber-600 tabular-nums">
                                {{ (stats.ballots_scanned ?? 0).toLocaleString() }} / {{ (stats.total_voters ?? totalScanned).toLocaleString() }}
                            </span>
                        </div>
                        <p class="text-2xl font-bold text-amber-800 tabular-nums leading-none mb-2">{{ stats.voter_turnout ?? 0 }}%</p>
                        <div class="h-1.5 rounded-full bg-amber-100 overflow-hidden">
                            <div class="h-full rounded-full bg-amber-500 transition-all duration-500" :style="{ width: `${Math.max(totalScanned ? 2 : 0, stats.voter_turnout ?? 0)}%` }"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Submission Quality + Top Candidates ──────────────── -->
            <div v-if="tallyData" class="grid gap-4 xl:grid-cols-2 mb-6 items-start">
                <!-- LEFT COLUMN: stack Submission Quality and Facilitators -->
                <div class="flex flex-col gap-4 h-full">
                    <div class="rounded-xl border border-slate-200 bg-white p-4 flex-none">
                        <h2 class="text-base font-semibold text-slate-900 mb-4">Submission Quality</h2>
                        <div class="space-y-4">
                            <div>
                                <div class="flex items-center justify-between text-sm mb-1.5">
                                    <span class="flex items-center gap-1.5 text-slate-600">
                                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                        Valid
                                    </span>
                                    <span class="font-semibold text-slate-900">
                                        {{ tallyData.summary.valid_submissions.toLocaleString() }}
                                        <span class="ml-1 font-normal text-slate-400">{{ validPct }}%</span>
                                    </span>
                                </div>
                                <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
                                    <div class="h-full rounded-full bg-emerald-500 transition-all duration-500" :style="{ width: `${Math.max(2, (tallyData.summary.valid_submissions / Math.max(1, tallyData.summary.total_scanned)) * 100)}%` }"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex items-center justify-between text-sm mb-1.5">
                                    <span class="flex items-center gap-1.5 text-slate-600">
                                        <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                                        Flagged / Invalid
                                    </span>
                                    <span class="font-semibold text-slate-900">
                                        {{ tallyData.summary.flagged_submissions.toLocaleString() }}
                                        <span class="ml-1 font-normal text-slate-400">{{ invalidPct }}%</span>
                                    </span>
                                </div>
                                <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
                                    <div class="h-full rounded-full bg-rose-500 transition-all duration-500" :style="{ width: `${Math.max(2, (tallyData.summary.flagged_submissions / Math.max(1, tallyData.summary.total_scanned)) * 100)}%` }"></div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 flex items-start gap-2 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2.5">
                                <i class="bi bi-info-circle mt-0.5 text-slate-400 text-sm leading-none" aria-hidden="true"></i>
                            <p class="text-xs text-slate-500">{{ submissionInsight }}</p>
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-4 flex-1 overflow-auto">
                        <h2 class="text-base font-semibold text-slate-900 mb-4">Facilitators</h2>
                        <div v-if="selectedElection?.facilitators?.length" class="space-y-2">
                            <div v-for="fac in selectedElection.facilitators" :key="fac.id" class="flex items-center justify-between">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-slate-800 truncate" :title="fac.name">{{ fac.name }}</p>
                                </div>
                                <div class="text-xs text-slate-500">Facilitator</div>
                            </div>
                        </div>
                        <div v-else class="text-sm text-slate-500">No facilitators assigned.</div>
                    </div>
                </div>

                <!-- RIGHT COLUMN: Top Candidates -->
                <div class="h-full">
                    <div class="rounded-xl border border-slate-200 bg-white p-4 h-full">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-base font-semibold text-slate-900">Top Candidates</h2>
                            <Link href="/admin/reports" class="text-xs font-medium text-indigo-600 hover:text-indigo-800 transition-colors">View all →</Link>
                        </div>
                        <div class="space-y-3">
                            <div v-for="(candidate, index) in tallyData.top_candidates" :key="candidate.id" class="flex items-center gap-3">
                                <div class="w-40 flex-shrink-0 min-w-0 flex items-center gap-2">
                                    <span class="h-2.5 w-2.5 flex-shrink-0 rounded-full" :style="{ backgroundColor: chartColorForCandidate(candidate, index) }"></span>
                                    <div class="min-w-0">
                                        <p class="text-xs font-medium text-slate-800 truncate" :title="candidate.name">{{ candidate.name }}</p>
                                        <p v-if="candidate.party" class="text-xs text-slate-400 truncate" :title="candidate.party">{{ candidate.party }}</p>
                                    </div>
                                </div>
                                <div class="flex-1 h-2 rounded-full bg-slate-100 overflow-hidden">
                                    <div class="h-full rounded-full transition-all duration-500" :style="{ width: `${Math.round((Number(candidate.votes) / topCandidateMaxVotes) * 100)}%`, backgroundColor: chartColorForCandidate(candidate, index) }"></div>
                                </div>
                                <span class="w-10 flex-shrink-0 text-right text-xs font-semibold text-slate-900 tabular-nums">{{ candidate.votes }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


        </div>
                <!-- ── Position Tallies (responsive chart grid) ─────────── -->
        <div v-if="tallyData" class="ui-card mt-8">
            <h2 class="text-base font-semibold text-slate-900 mb-4">Position Tallies</h2>

            <div
                ref="positionGridRef"
                class="grid gap-4 transition-[grid-template-columns] duration-300"
                :class="gridClass"
            >
                <div v-for="position in positionTallies" :key="position.position_id" class="rounded-xl border border-slate-200 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                        <h3 class="font-semibold text-slate-900">{{ position.position_name }}</h3>
                        <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-200">
                            {{ (position.total_votes ?? 0).toLocaleString() }} votes
                        </span>
                    </div>

                    <div class="h-60">
                        <canvas :ref="(element) => setPositionChartCanvas(position.position_id, element)"></canvas>
                    </div>

                    <div class="mt-3 space-y-2">
                        <div
                            v-for="(candidate, index) in position.candidates"
                            :key="candidate.id"
                            class="flex items-center gap-2"
                        >
                            <span
                                class="h-2.5 w-2.5 flex-shrink-0 rounded-full"
                                :style="{ backgroundColor: chartColorForCandidate(candidate, index) }"
                            ></span>
                            <span class="flex-1 truncate text-xs text-slate-600 min-w-0">
                                {{ candidate.name }}
                                <span v-if="candidate.party" class="text-slate-400">({{ candidate.party }})</span>
                            </span>
                            <span class="flex-shrink-0 text-xs font-semibold text-slate-900 tabular-nums">
                                {{ (candidate.votes ?? 0).toLocaleString() }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Change Election Modal ──────────────────────────── -->
        <Teleport to="body">
            <div
                v-if="showElectionModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                @click.self="showElectionModal = false"
            >
                <div class="rounded-2xl bg-white shadow-xl max-w-md w-full max-h-96 flex flex-col overflow-hidden">
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                        <h2 class="text-lg font-semibold text-slate-900">Select Election</h2>
                        <button
                            @click="showElectionModal = false"
                            class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors"
                            aria-label="Close modal"
                        >
                            <i class="bi bi-x text-lg leading-none" aria-hidden="true"></i>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="flex-1 overflow-y-auto">
                        <div v-if="availableElections?.length" class="divide-y divide-slate-100">
                            <Link
                                v-for="election in availableElections"
                                :key="election.id"
                                :href="`/admin?election=${election.id}`"
                                class="block px-6 py-3.5 hover:bg-slate-50 transition-colors text-left"
                            >
                                <div class="flex items-center justify-between">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-slate-900">{{ election.label }}</p>
                                        <p class="text-xs text-slate-500 mt-0.5">
                                            {{ election.election_date_formatted }}
                                        </p>
                                    </div>
                                    <span
                                        v-if="election.id === selectedElection?.id"
                                        class="flex-shrink-0 inline-flex items-center justify-center h-6 w-6 rounded-full bg-indigo-100"
                                    >
                                        <i class="bi bi-check-lg text-indigo-600 text-sm leading-none" aria-hidden="true"></i>
                                    </span>
                                </div>
                            </Link>
                        </div>
                        <div v-else class="flex items-center justify-center py-8 text-slate-500">
                            <p class="text-sm">No elections available</p>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>