<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    report: Object,
    reportData: Object,
});

const winnersByPosition = computed(() => props.reportData?.winners ?? []);
const hasWinners = computed(() => Array.isArray(winnersByPosition.value) && winnersByPosition.value.length > 0);
const tiePositions = computed(() => winnersByPosition.value.filter((position) => position.has_tie));

const tieSelections = ref({});
const tieNotes = ref({});
const tieErrors = ref({});
const tieSaving = ref({});

const seatsRequired = (position) => Number(position.seats_remaining ?? position.votes_allowed ?? 1);
const getSelectedIds = (positionId) => tieSelections.value[positionId] ?? [];

const toggleTieSelection = (position, candidateId) => {
    const positionId = String(position.position_id);
    const limit = seatsRequired(position);
    const current = new Set(getSelectedIds(positionId));

    if (current.has(candidateId)) {
        current.delete(candidateId);
    } else {
        if (current.size >= limit) {
            tieErrors.value = {
                ...tieErrors.value,
                [positionId]: `Select exactly ${limit} winner(s).`,
            };
            return;
        }
        current.add(candidateId);
    }

    tieSelections.value = {
        ...tieSelections.value,
        [positionId]: Array.from(current),
    };
    tieErrors.value = {
        ...tieErrors.value,
        [positionId]: '',
    };
};

const submitTieResolution = async (position) => {
    const positionId = String(position.position_id);
    const limit = seatsRequired(position);
    const selected = getSelectedIds(positionId);

    if (selected.length !== limit) {
        tieErrors.value = {
            ...tieErrors.value,
            [positionId]: `Select exactly ${limit} winner(s) to resolve this tie.`,
        };
        return;
    }

    tieSaving.value = { ...tieSaving.value, [positionId]: true };
    tieErrors.value = { ...tieErrors.value, [positionId]: '' };

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    try {
        const response = await fetch(`/admin/reports/${props.report.id}/resolve-tie`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                Accept: 'application/json',
            },
            body: JSON.stringify({
                position_id: position.position_id,
                winner_ids: selected,
                note: tieNotes.value[positionId] || null,
            }),
        });

        const payload = await response.json();
        if (!response.ok || payload?.success === false) {
            throw new Error(payload?.message || 'Unable to save tie resolution.');
        }

        router.reload({ preserveScroll: true });
    } catch (error) {
        tieErrors.value = {
            ...tieErrors.value,
            [positionId]: error?.message || 'Unable to save tie resolution.',
        };
    } finally {
        tieSaving.value = { ...tieSaving.value, [positionId]: false };
    }
};

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

            <div v-if="tiePositions.length" class="mb-10 rounded-2xl border border-amber-200 bg-amber-50/40 p-5">
                <h2 class="text-lg font-bold text-amber-900 mb-2">Resolve Ties</h2>
                <p class="text-sm text-amber-800 mb-4">Select the final winner(s) for each tied position and save the resolution.</p>

                <div class="space-y-4">
                    <div v-for="position in tiePositions" :key="position.position_id" class="rounded-xl border border-amber-200 bg-white p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">{{ position.position_name }}</p>
                                <p class="text-xs text-slate-500">Tie at {{ position.tied_vote_count }} votes</p>
                            </div>
                            <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">
                                Select {{ seatsRequired(position) }} winner(s)
                            </span>
                        </div>

                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                            <label v-for="candidate in position.tied_candidates ?? []" :key="candidate.id"
                                class="flex items-center justify-between gap-3 rounded-lg border px-3 py-2 text-sm cursor-pointer"
                                :class="getSelectedIds(String(position.position_id)).includes(candidate.id)
                                    ? 'border-emerald-300 bg-emerald-50'
                                    : 'border-slate-200 bg-white'">
                                <div class="flex items-center gap-3">
                                    <input
                                        type="checkbox"
                                        class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                        :checked="getSelectedIds(String(position.position_id)).includes(candidate.id)"
                                        @change="toggleTieSelection(position, candidate.id)"
                                    />
                                    <div>
                                        <p class="font-semibold text-slate-900">{{ candidate.name }}</p>
                                        <p class="text-xs text-slate-500">{{ candidate.party || '-' }}</p>
                                    </div>
                                </div>
                                <span class="text-xs font-semibold text-slate-600">{{ candidate.votes }} votes</span>
                            </label>
                        </div>

                        <p v-if="(position.tied_candidates ?? []).length === 0" class="mt-3 text-sm text-amber-700">
                            No tied candidates were recorded in this report. Regenerate the report to resolve this tie.
                        </p>

                        <div class="mt-3">
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Resolution note (optional)</label>
                            <textarea
                                v-model="tieNotes[String(position.position_id)]"
                                rows="2"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500"
                                placeholder="Document the basis for this tie resolution..."
                            ></textarea>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                            <p class="text-xs text-slate-500">
                                Selected {{ getSelectedIds(String(position.position_id)).length }} / {{ seatsRequired(position) }}
                            </p>
                            <button
                                type="button"
                                class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700 disabled:opacity-60"
                                :disabled="tieSaving[String(position.position_id)] || (position.tied_candidates ?? []).length === 0"
                                @click="submitTieResolution(position)"
                            >
                                {{ tieSaving[String(position.position_id)] ? 'Saving...' : 'Save Resolution' }}
                            </button>
                        </div>

                        <p v-if="tieErrors[String(position.position_id)]" class="mt-2 text-sm text-amber-700">
                            {{ tieErrors[String(position.position_id)] }}
                        </p>
                    </div>
                </div>
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
                                <tr v-if="position.has_tie" class="bg-amber-50/70">
                                    <td colspan="4" class="px-6 py-3 text-sm font-semibold text-amber-800">
                                        Tie detected at {{ position.tied_vote_count }} votes. Manual resolution required for {{ position.seats_remaining ?? position.votes_allowed }} seat(s).
                                    </td>
                                </tr>
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