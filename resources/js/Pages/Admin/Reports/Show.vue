<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

defineProps({
    report: Object,
    reportData: Object,
});
</script>

<template>
    <Head title="Report Details" />

    <div class="ui-page">
        <div class="ui-card">
            <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
                <div>
                    <h1 class="text-xl font-semibold">Report Details</h1>
                    <p class="text-slate-600">{{ report.election?.label ?? 'Unknown election' }} • Generated {{ report.generated_date_formatted ?? report.generated_date }}</p>
                </div>
                <Link href="/admin/reports" class="ui-btn-secondary">Back to Reports</Link>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 mb-6">
                <div class="rounded-xl border border-blue-200 bg-blue-50 p-4"><div class="text-xs uppercase tracking-wide text-blue-700">Scanned Ballots</div><div class="text-3xl font-semibold text-blue-900">{{ reportData.summary?.total_scanned ?? 0 }}</div></div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4"><div class="text-xs uppercase tracking-wide text-emerald-700">Valid Submissions</div><div class="text-3xl font-semibold text-emerald-900">{{ reportData.summary?.valid_submissions ?? 0 }}</div></div>
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4"><div class="text-xs uppercase tracking-wide text-amber-700">Flagged Submissions</div><div class="text-3xl font-semibold text-amber-900">{{ reportData.summary?.flagged_submissions ?? 0 }}</div></div>
                <div class="rounded-xl border border-violet-200 bg-violet-50 p-4"><div class="text-xs uppercase tracking-wide text-violet-700">Turnout</div><div class="text-3xl font-semibold text-violet-900">{{ reportData.summary?.turnout_percent ?? 0 }}%</div></div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <h2 class="text-base font-semibold text-slate-900 mb-3">Stored Candidate Tallies</h2>
                <div class="space-y-5">
                    <div v-for="position in reportData.position_tallies ?? []" :key="position.position_id" class="rounded-xl border border-slate-200 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                            <h3 class="font-semibold text-slate-900">{{ position.position_name }}</h3>
                            <span class="text-xs text-slate-500">Total votes: {{ position.total_votes }}</span>
                        </div>
                        <div class="space-y-2">
                            <div v-for="candidate in position.candidates" :key="candidate.id" class="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-sm">
                                <span>{{ candidate.name }} <span v-if="candidate.party" class="text-xs text-slate-500">({{ candidate.party }})</span></span>
                                <span class="font-semibold text-slate-900">{{ candidate.votes }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>