<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    elections: Array,
    selectedElectionId: Number,
    reports: Object,
});

const filterElectionId = ref(props.selectedElectionId ?? '');

watch(() => props.selectedElectionId, (value) => {
    filterElectionId.value = value ?? '';
});

function applyFilter() {
    router.get('/admin/reports', { election: filterElectionId.value || null }, { preserveState: true, preserveScroll: true, replace: true });
}

const selectedElection = computed(() => props.elections.find((election) => election.id === props.selectedElectionId) ?? null);
</script>

<template>
    <Head title="Election Reports" />

    <div class="ui-page">
        <div class="ui-card">
            <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
                <div>
                    <h1 class="text-xl font-semibold">Election Reports</h1>
                    <p class="text-slate-600">Generate and store snapshots of election tally results.</p>
                </div>
            </div>

            <div v-if="$page.props.flash?.status" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-emerald-900">
                {{ $page.props.flash.status }}
            </div>

            <div v-if="elections.length === 0" class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-amber-900">
                No elections found yet. Create an election first.
            </div>

            <div v-else class="grid gap-4 lg:grid-cols-2 mb-6">
                <div class="rounded-xl border border-slate-200 p-4">
                    <h2 class="font-semibold text-slate-900 mb-3">Filter Reports</h2>
                    <label class="block text-sm font-medium mb-1" for="election">Election scope</label>
                    <select id="election" v-model="filterElectionId" class="ui-input mb-3">
                        <option value="">All elections</option>
                        <option v-for="election in elections" :key="election.id" :value="election.id">
                            {{ election.label }} ({{ election.status }})
                        </option>
                    </select>
                    <button type="button" class="ui-btn-primary w-full sm:w-auto" @click="applyFilter">Apply filter</button>
                </div>

                <form method="POST" action="/admin/reports/generate" class="rounded-xl border border-slate-200 p-4">
                    <input type="hidden" name="_token" :value="$page.props.csrf_token ?? ''" />
                    <h2 class="font-semibold text-slate-900 mb-3">Generate New Report</h2>
                    <label for="election_id" class="block text-sm font-medium mb-1">Election</label>
                    <select id="election_id" name="election_id" class="ui-input mb-2" required>
                        <option v-for="election in elections" :key="election.id" :value="election.id" :selected="selectedElectionId === election.id">
                            {{ election.label }} ({{ election.status }})
                        </option>
                    </select>
                    <button type="submit" class="ui-btn-primary w-full sm:w-auto">Generate report snapshot</button>
                </form>
            </div>

            <div class="rounded-xl border border-slate-200 overflow-hidden">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th class="ui-th">Generated</th>
                            <th class="ui-th">Election</th>
                            <th class="ui-th">Status</th>
                            <th class="ui-th text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="report in reports.data" :key="report.id" class="ui-row">
                            <td class="ui-td">{{ report.generated_date_formatted ?? report.generated_date }}</td>
                            <td class="ui-td">{{ report.election?.label ?? 'Unknown election' }}</td>
                            <td class="ui-td"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium bg-slate-100 text-slate-700">{{ report.election?.status ?? 'unknown' }}</span></td>
                            <td class="ui-td text-right">
                                <Link :href="`/admin/reports/${report.id}`" class="ui-btn-secondary ui-btn-sm" title="View report details" aria-label="View report details">
                                    <i class="bi bi-eye"></i>
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="reports.data.length === 0">
                            <td colspan="4" class="ui-td text-center text-slate-500 py-8">No reports found for the selected filter.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>