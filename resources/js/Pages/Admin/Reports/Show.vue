<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    report: Object,
    reportData: Object,
});

const winnersByPosition = computed(() => props.reportData?.winners ?? []);
const hasWinners = computed(() => Array.isArray(winnersByPosition.value) && winnersByPosition.value.length > 0);

const getPercent = (votes, total) => {
    if (!total || total === 0) return 0;
    return ((votes / total) * 100).toFixed(1);
};
</script>

<template>
    <Head title="Report Details" />

    <div class="ui-page">
        <div class="ui-card">
            <div class="flex flex-wrap items-start justify-between gap-4 mb-8 border-b border-slate-100 pb-6">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Election Report</h1>
                    <p class="text-slate-500 mt-1">{{ report.election?.label ?? 'Unknown election' }} • Generated {{ report.generated_date_formatted ?? report.generated_date }}</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <Link href="/admin/reports" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">Back to Reports</Link>
                    <Link :href="`/admin/reports/${report.id}/print`" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors shadow-sm">Print Layout Preview</Link>
                </div>
            </div>

            <div class="mb-10 overflow-hidden rounded-xl border border-slate-200 shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-center">
                    <thead class="bg-slate-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider border-r border-slate-200 w-1/4">Scanned Ballots</th>
                            <th scope="col" class="px-6 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider border-r border-slate-200 w-1/4">Valid Submissions</th>
                            <th scope="col" class="px-6 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider border-r border-slate-200 w-1/4">Flagged Submissions</th>
                            <th scope="col" class="px-6 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider w-1/4">Voter Turnout</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white">
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-2xl font-bold text-blue-700 border-r border-slate-200">
                                {{ reportData.summary?.total_scanned ?? 0 }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-2xl font-bold text-emerald-700 border-r border-slate-200">
                                {{ reportData.summary?.valid_submissions ?? 0 }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-2xl font-bold text-amber-700 border-r border-slate-200">
                                {{ reportData.summary?.flagged_submissions ?? 0 }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-2xl font-bold text-violet-700">
                                {{ reportData.summary?.turnout_percent ?? 0 }}%
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="hasWinners" class="mb-10">
                <h2 class="text-lg font-bold text-slate-900 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                    Declared Winners
                </h2>
                <div class="overflow-hidden rounded-xl border border-amber-200 shadow-sm">
                    <table class="min-w-full divide-y divide-amber-200">
                        <thead class="bg-amber-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-amber-800 uppercase tracking-wider">Position</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-amber-800 uppercase tracking-wider">Winner Name</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-amber-800 uppercase tracking-wider">Party</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-bold text-amber-800 uppercase tracking-wider">Total Votes</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-100">
                            <template v-for="position in winnersByPosition" :key="position.position_id">
                                <tr v-for="(winner, index) in position.winners" :key="winner.id" class="hover:bg-slate-50 transition-colors">
                                    <td v-if="index === 0" :rowspan="position.winners.length" class="px-6 py-4 whitespace-nowrap text-sm font-bold text-slate-800 border-r border-slate-100 align-top bg-slate-50/50">
                                        {{ position.position_name }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900">
                                        ⭐ {{ winner.name }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                                        {{ winner.party || '-' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold text-emerald-600">
                                        {{ winner.votes }}
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <h2 class="text-lg font-bold text-slate-900 mb-4">Complete Position Tallies</h2>
                <div class="overflow-hidden rounded-xl border border-slate-200 shadow-sm">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Candidate</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Party</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Votes</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">%</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider w-1/4">Vote Share</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-100">
                            <template v-for="position in reportData.position_tallies ?? []" :key="position.position_id">
                                <tr class="bg-slate-100/80">
                                    <td colspan="5" class="px-6 py-2 text-sm">
                                        <span class="font-bold text-slate-800">{{ position.position_name }}</span> 
                                        <span class="ml-2 text-xs font-medium text-slate-500 border border-slate-200 bg-white rounded px-2 py-0.5">Total Votes: {{ position.total_votes }}</span>
                                    </td>
                                </tr>
                                <tr v-for="candidate in position.candidates" :key="candidate.id" class="hover:bg-slate-50 transition-colors">
                                    <td class="px-6 py-3 whitespace-nowrap text-sm font-medium text-slate-900 pl-10">
                                        {{ candidate.name }}
                                    </td>
                                    <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-500">
                                        {{ candidate.party || '-' }}
                                    </td>
                                    <td class="px-6 py-3 whitespace-nowrap text-sm text-right font-semibold text-slate-700">
                                        {{ candidate.votes }}
                                    </td>
                                    <td class="px-6 py-3 whitespace-nowrap text-sm text-right text-slate-500">
                                        {{ getPercent(candidate.votes, position.total_votes) }}%
                                    </td>
                                    <td class="px-6 py-3 whitespace-nowrap align-middle">
                                        <div class="w-full bg-slate-200 rounded-full h-2">
                                            <div class="bg-blue-500 h-2 rounded-full" :style="{ width: `${getPercent(candidate.votes, position.total_votes)}%` }"></div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>