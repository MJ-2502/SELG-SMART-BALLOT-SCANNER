<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    hasElection: Boolean,
    selectedElection: Object,
    stats: Object,
    tallyData: Object,
});

const positionTallies = computed(() => props.tallyData?.position_tallies ?? []);
</script>

<template>
    <Head title="Dashboard" />

    <div class="ui-page">
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
            <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-slate-900">Dashboard</h1>
                    <p class="text-slate-600 mt-1">Monitoring for {{ selectedElection?.label }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <Link href="/admin/progress" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Live Monitoring</Link>
                    <Link href="/admin/reports" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reports</Link>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 mb-6">
                <div class="rounded-2xl border border-slate-200 bg-white/90 p-5 shadow-sm"><div class="text-sm text-slate-500 mb-1">Total Positions</div><div class="text-3xl font-semibold text-slate-900">{{ stats.total_positions }}</div></div>
                <div class="rounded-2xl border border-slate-200 bg-white/90 p-5 shadow-sm"><div class="text-sm text-slate-500 mb-1">Total Candidates</div><div class="text-3xl font-semibold text-slate-900">{{ stats.total_candidates }}</div></div>
                <div class="rounded-2xl border border-slate-200 bg-white/90 p-5 shadow-sm"><div class="text-sm text-slate-500 mb-1">Ballots Scanned</div><div class="text-3xl font-semibold text-slate-900">{{ stats.ballots_scanned ?? 0 }}</div></div>
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm"><div class="text-sm text-emerald-700 mb-1">Valid Ballots</div><div class="text-3xl font-semibold text-emerald-900">{{ stats.valid_ballots ?? 0 }}</div></div>
                <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 shadow-sm"><div class="text-sm text-rose-700 mb-1">Invalid Ballots</div><div class="text-3xl font-semibold text-rose-900">{{ stats.invalid_ballots ?? 0 }}</div></div>
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm"><div class="text-sm text-amber-700 mb-1">Voter Turnout</div><div class="text-3xl font-semibold text-amber-900">{{ stats.voter_turnout ?? 0 }}%</div></div>
            </div>

            <div v-if="tallyData" class="grid gap-4 xl:grid-cols-2 mb-6">
                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <h2 class="text-base font-semibold text-slate-900 mb-3">Submission Quality</h2>
                    <div class="space-y-3">
                        <div>
                            <div class="flex items-center justify-between text-sm text-slate-600 mb-1"><span>Valid</span><span class="font-semibold text-slate-900">{{ tallyData.summary.valid_submissions }}</span></div>
                            <div class="h-2.5 rounded-full bg-slate-100 overflow-hidden"><div class="h-full rounded-full bg-emerald-500" :style="{ width: `${Math.max(5, (tallyData.summary.valid_submissions / Math.max(1, tallyData.summary.total_scanned)) * 100)}%` }"></div></div>
                        </div>
                        <div>
                            <div class="flex items-center justify-between text-sm text-slate-600 mb-1"><span>Flagged</span><span class="font-semibold text-slate-900">{{ tallyData.summary.flagged_submissions }}</span></div>
                            <div class="h-2.5 rounded-full bg-slate-100 overflow-hidden"><div class="h-full rounded-full bg-amber-500" :style="{ width: `${Math.max(5, (tallyData.summary.flagged_submissions / Math.max(1, tallyData.summary.total_scanned)) * 100)}%` }"></div></div>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <h2 class="text-base font-semibold text-slate-900 mb-3">Top Candidates</h2>
                    <div class="space-y-2">
                        <div v-for="candidate in tallyData.top_candidates" :key="candidate.id" class="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50 px-3 py-2">
                            <span class="text-sm text-slate-700 truncate">{{ candidate.name }} <span v-if="candidate.party" class="text-xs text-slate-500">({{ candidate.party }})</span></span>
                            <span class="text-sm font-semibold text-slate-900">{{ candidate.votes }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="tallyData" class="rounded-xl border border-slate-200 bg-white p-4">
                <h2 class="text-base font-semibold text-slate-900 mb-3">Position Tallies</h2>
                <div class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
                    <div v-for="position in positionTallies" :key="position.position_id" class="rounded-xl border border-slate-200 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                            <h3 class="font-semibold text-slate-900">{{ position.position_name }}</h3>
                            <span class="text-xs text-slate-500">Total votes: {{ position.total_votes }}</span>
                        </div>
                        <div class="space-y-2">
                            <div v-for="candidate in position.candidates" :key="candidate.id">
                                <div class="flex items-center justify-between text-sm text-slate-600 mb-1">
                                    <span class="truncate">{{ candidate.name }} <span v-if="candidate.party" class="text-xs text-slate-500">({{ candidate.party }})</span></span>
                                    <span class="font-medium text-slate-900">{{ candidate.votes }}</span>
                                </div>
                                <div class="h-2.5 w-full rounded-full bg-slate-100 overflow-hidden">
                                    <div class="h-full rounded-full bg-indigo-500 transition-all duration-300" :style="{ width: `${Math.max(2, (candidate.votes / Math.max(1, Math.max(...position.candidates.map((item) => item.votes)))) * 100)}%` }"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>