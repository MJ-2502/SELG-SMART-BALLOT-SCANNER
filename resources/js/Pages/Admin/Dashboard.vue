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
});

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
    <Head title="Dashboard" />

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
                    <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-slate-900">Dashboard</h1>
                    <!-- Election name + live status badge -->
                    <div class="flex flex-wrap items-center gap-2 mt-1.5">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset"
                            :class="electionStatus.classes"
                        >
                            <span class="h-1.5 w-1.5 rounded-full" :class="electionStatus.dotClass"></span>
                            {{ electionStatus.label }}
                        </span>
                        <span class="text-sm text-slate-500">{{ selectedElection?.label }}</span>
                        <span v-if="selectedElection?.election_date_formatted" class="text-xs text-slate-400">
                            · {{ selectedElection.election_date_formatted }}
                        </span>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Link
                        href="/admin/progress"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        Live Monitoring
                    </Link>
                    <Link
                        href="/admin/reports"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Reports
                    </Link>
                </div>
            </div>

            <!-- ── Stat cards ──────────────────────────────────────── -->
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 mb-6">

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-5">
                    <div class="flex items-center gap-1.5 text-sm text-slate-500 mb-2">
                        <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h18M3 12h18M3 17h18"/></svg>
                        Total Positions
                    </div>
                    <div class="text-3xl font-semibold text-slate-900 tabular-nums">{{ stats.total_positions ?? 0 }}</div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-5">
                    <div class="flex items-center gap-1.5 text-sm text-slate-500 mb-2">
                        <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0"/></svg>
                        Total Candidates
                    </div>
                    <div class="text-3xl font-semibold text-slate-900 tabular-nums">{{ stats.total_candidates ?? 0 }}</div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-5">
                    <div class="flex items-center gap-1.5 text-sm text-slate-500 mb-2">
                        <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75m-7.5 5.25h13.5A2.25 2.25 0 0021 20.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V19.5a2.25 2.25 0 002.25 2.25h.75"/></svg>
                        Ballots Scanned
                    </div>
                    <div class="text-3xl font-semibold text-slate-900 tabular-nums">{{ (stats.ballots_scanned ?? 0).toLocaleString() }}</div>
                </div>

                <!-- Valid — green, with percentage subtitle -->
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-5">
                    <div class="flex items-center gap-1.5 text-sm text-emerald-700 mb-2">
                        <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Valid Ballots
                    </div>
                    <div class="text-3xl font-semibold text-emerald-900 tabular-nums">{{ (stats.valid_ballots ?? 0).toLocaleString() }}</div>
                    <div v-if="totalScanned" class="text-xs text-emerald-600 mt-1.5">{{ validPct }}% of total scanned</div>
                </div>

                <!-- Invalid — rose, with percentage subtitle -->
                <div class="rounded-xl border border-rose-200 bg-rose-50 p-5">
                    <div class="flex items-center gap-1.5 text-sm text-rose-700 mb-2">
                        <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                        Invalid Ballots
                    </div>
                    <div class="text-3xl font-semibold text-rose-900 tabular-nums">{{ (stats.invalid_ballots ?? 0).toLocaleString() }}</div>
                    <div v-if="totalScanned" class="text-xs text-rose-600 mt-1.5">{{ invalidPct }}% of total scanned</div>
                </div>

                <!-- Voter Turnout — amber, with voter count subtitle -->
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-5">
                    <div class="flex items-center gap-1.5 text-sm text-amber-700 mb-2">
                        <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
                        Voter Turnout
                    </div>
                    <div class="text-3xl font-semibold text-amber-900 tabular-nums">{{ stats.voter_turnout ?? 0 }}%</div>
                    <div v-if="totalScanned" class="text-xs text-amber-600 mt-1.5">
                        {{ (stats.ballots_scanned ?? 0).toLocaleString() }} / {{ (stats.total_voters ?? totalScanned).toLocaleString() }} voters
                    </div>
                </div>
            </div>

            <!-- ── Submission Quality + Top Candidates ──────────────── -->
            <div v-if="tallyData" class="grid gap-4 xl:grid-cols-2 mb-6">

                <!-- Submission Quality -->
                <div class="rounded-xl border border-slate-200 bg-white p-4">
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
                                <div
                                    class="h-full rounded-full bg-emerald-500 transition-all duration-500"
                                    :style="{ width: `${Math.max(2, (tallyData.summary.valid_submissions / Math.max(1, tallyData.summary.total_scanned)) * 100)}%` }"
                                ></div>
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
                                <div
                                    class="h-full rounded-full bg-rose-500 transition-all duration-500"
                                    :style="{ width: `${Math.max(2, (tallyData.summary.flagged_submissions / Math.max(1, tallyData.summary.total_scanned)) * 100)}%` }"
                                ></div>
                            </div>
                        </div>
                    </div>

                    <!-- Contextual insight line -->
                    <div class="mt-4 flex items-start gap-2 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2.5">
                        <svg class="mt-0.5 h-3.5 w-3.5 flex-shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-xs text-slate-500">{{ submissionInsight }}</p>
                    </div>
                </div>

                <!-- Top Candidates — inline proportional bars -->
                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-base font-semibold text-slate-900">Top Candidates</h2>
                        <Link href="/admin/reports" class="text-xs font-medium text-indigo-600 hover:text-indigo-800 transition-colors">
                            View all →
                        </Link>
                    </div>
                    <div class="space-y-3">
                        <div
                            v-for="candidate in tallyData.top_candidates"
                            :key="candidate.id"
                            class="flex items-center gap-3"
                        >
                            <div class="w-32 flex-shrink-0 min-w-0">
                                <p class="text-xs font-medium text-slate-800 truncate">{{ candidate.name }}</p>
                                <p v-if="candidate.party" class="text-xs text-slate-400 truncate">{{ candidate.party }}</p>
                            </div>
                            <div class="flex-1 h-2 rounded-full bg-slate-100 overflow-hidden">
                                <div
                                    class="h-full rounded-full bg-indigo-500 transition-all duration-500"
                                    :style="{ width: `${Math.round((Number(candidate.votes) / topCandidateMaxVotes) * 100)}%` }"
                                ></div>
                            </div>
                            <span class="w-10 flex-shrink-0 text-right text-xs font-semibold text-slate-900 tabular-nums">
                                {{ candidate.votes }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Position Tallies (responsive chart grid) ─────────── -->
            <div v-if="tallyData" class="rounded-xl border border-slate-200 bg-white p-4">
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
        </div>
    </div>
</template>