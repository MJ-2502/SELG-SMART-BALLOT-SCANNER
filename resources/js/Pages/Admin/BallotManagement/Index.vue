<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({ elections: Array, selectedElection: Object, ballots: Object, statusCounts: Object });

const confirmDelete = () => window.confirm('Delete this ballot?');

const isLocked = (ballot) => {
    if (!props.selectedElection) {
        return true;
    }

    const finished = props.selectedElection.status === 'completed';
    const pendingNoVotes = ballot.status === 'pending' && Number(ballot.votes_count) === 0;

    return !(finished && pendingNoVotes);
};
</script>

<template>
    <Head title="Ballot Management" />
    <div class="ui-page">
        <div class="ui-card">
            <div class="flex items-center justify-between gap-3 mb-6">
                <div>
                    <h1 class="text-xl font-semibold">Ballot Management</h1>
                    <p class="text-gray-600">Manage generated ballots by election.</p>
                </div>
                <div class="flex gap-2">
                    <Link href="/admin/ballot-generator" class="ui-btn-secondary">Open Ballot Generator</Link>
                    <a v-if="selectedElection" :href="`/admin/ballot-generator/print?election=${selectedElection.id}`" class="ui-btn-primary">Open Print Layout</a>
                </div>
            </div>

            <div v-if="$page.props.flash.status" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-emerald-900">
                {{ $page.props.flash.status }}
            </div>

            <div v-if="$page.props.flash.error" class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-red-800">
                {{ $page.props.flash.error }}
            </div>

            <div v-if="elections.length === 0" class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-amber-900">
                No elections found yet. Create an election first.
            </div>

            <template v-else>
                <form method="GET" action="/admin/ballot-management" class="grid gap-3 md:grid-cols-[1fr_auto] mb-6">
                    <div>
                        <label for="election" class="block text-sm font-medium mb-1">Election</label>
                        <select id="election" name="election" class="ui-input">
                            <option v-for="election in elections" :key="election.id" :value="election.id" :selected="selectedElection?.id === election.id">
                                {{ election.label }} ({{ election.status }}, Ballots: {{ election.ballots_count }})
                            </option>
                        </select>
                    </div>
                    <div class="md:self-end">
                        <button type="submit" class="ui-btn-primary w-full md:w-auto">Load Ballots</button>
                    </div>
                </form>

                <p v-if="selectedElection" class="text-sm text-gray-500 mb-4">
                    Delete is enabled only for pending generated ballots in past or finished elections.
                </p>

                <div v-if="selectedElection" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 mb-6">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <div class="text-xs uppercase tracking-wide text-slate-500">Total</div>
                        <div class="text-2xl font-semibold text-slate-900">{{ ballots.total }}</div>
                    </div>
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <div class="text-xs uppercase tracking-wide text-amber-700">Pending</div>
                        <div class="text-2xl font-semibold text-amber-900">{{ statusCounts.pending ?? 0 }}</div>
                    </div>
                    <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                        <div class="text-xs uppercase tracking-wide text-blue-700">Scanned</div>
                        <div class="text-2xl font-semibold text-blue-900">{{ statusCounts.scanned ?? 0 }}</div>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-100 p-4">
                        <div class="text-xs uppercase tracking-wide text-slate-600">Other</div>
                        <div class="text-2xl font-semibold text-slate-900">{{ Math.max(0, ballots.total - ((statusCounts.pending ?? 0) + (statusCounts.scanned ?? 0))) }}</div>
                    </div>
                </div>

                <div v-if="ballots.data.length === 0" class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-slate-700">
                    No ballots found for this election.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-100 border-b">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium">Ballot #</th>
                                <th class="px-3 py-2 text-left font-medium">UUID</th>
                                <th class="px-3 py-2 text-left font-medium">Status</th>
                                <th class="px-3 py-2 text-left font-medium">Votes</th>
                                <th class="px-3 py-2 text-left font-medium">Scanned At</th>
                                <th class="px-3 py-2 text-left font-medium">Scanned By</th>
                                <th class="px-3 py-2 text-left font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="ballot in ballots.data" :key="ballot.id" class="border-b hover:bg-gray-50">
                                <td class="px-3 py-2">{{ ballot.ballot_number ?? '-' }}</td>
                                <td class="px-3 py-2 font-mono text-xs">{{ ballot.uuid }}</td>
                                <td class="px-3 py-2">
                                    <span v-if="ballot.status === 'pending'" class="inline-block px-2 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">Pending</span>
                                    <span v-else-if="ballot.status === 'scanned'" class="inline-block px-2 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">Scanned</span>
                                    <span v-else class="inline-block px-2 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-800">{{ ballot.status }}</span>
                                </td>
                                <td class="px-3 py-2">{{ ballot.votes_count }}</td>
                                <td class="px-3 py-2">{{ ballot.scanned_at_formatted ?? '-' }}</td>
                                <td class="px-3 py-2">{{ ballot.scanner?.name ?? '-' }}</td>
                                <td class="px-3 py-2">
                                    <form v-if="!isLocked(ballot)" :action="`/admin/ballot-management/${ballot.id}`" method="POST" @submit="(e) => { if (!confirmDelete()) e.preventDefault(); }">
                                        <input type="hidden" name="_token" :value="$page.props.csrf_token" />
                                        <input type="hidden" name="_method" value="DELETE" />
                                        <button type="submit" class="inline-block rounded px-2 py-1 text-xs font-semibold bg-red-500 text-white hover:bg-red-600" title="Delete ballot" aria-label="Delete ballot">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    <span v-else class="text-xs text-slate-500">Locked</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="ballots.links" class="mt-4">
                    <div class="flex flex-wrap gap-2">
                        <a
                            v-for="link in ballots.links"
                            :key="link.label"
                            :href="link.url || '#'"
                            :class="[
                                'px-3 py-1.5 rounded border text-sm',
                                link.active ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-700 border-slate-200',
                                !link.url ? 'pointer-events-none opacity-50' : 'hover:bg-slate-50',
                            ]"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>