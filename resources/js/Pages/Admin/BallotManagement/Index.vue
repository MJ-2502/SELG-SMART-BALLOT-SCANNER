<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Teleport } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({ elections: Array, selectedElection: Object, ballots: Object, statusCounts: Object });

const showGeneratorModal = ref(false);
const generatorForm = useForm({
    election_id: props.selectedElection?.id ?? '',
    print_count: 50,
});

const confirmDelete = () => window.confirm('Delete this ballot?');

const isLocked = (ballot) => {
    if (!props.selectedElection) {
        return true;
    }

    const finished = props.selectedElection.status === 'completed';
    const pendingNoVotes = ballot.status === 'pending' && Number(ballot.votes_count) === 0;

    return !(finished && pendingNoVotes);
};

const submitGeneratorForm = () => {
    generatorForm.post('/admin/ballot-generator/generate', {
        onSuccess: () => {
            showGeneratorModal.value = false;
            generatorForm.reset();
        },
    });
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
                    <button @click="showGeneratorModal = true" type="button" class="ui-btn-secondary">Open Ballot Generator</button>
                    <a v-if="selectedElection" :href="`/admin/ballot-management/print?election=${selectedElection.id}`" class="ui-btn-primary">Open Print Layout</a>
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
                    <div class="rounded-xl border border-rose-200 bg-rose-50 p-4">
                        <div class="text-xs uppercase tracking-wide text-rose-700">Flagged</div>
                        <div class="text-2xl font-semibold text-rose-900">{{ statusCounts.flagged ?? 0 }}</div>
                    </div>
                    <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                        <div class="text-xs uppercase tracking-wide text-blue-700">Scanned</div>
                        <div class="text-2xl font-semibold text-blue-900">{{ statusCounts.scanned ?? 0 }}</div>
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
                                    <span v-else-if="ballot.status === 'flagged'" class="inline-block px-2 py-1 rounded-full text-xs font-semibold bg-rose-100 text-rose-800">Flagged</span>
                                    <span v-else class="inline-block px-2 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-800">{{ ballot.status }}</span>
                                </td>
                                <td class="px-3 py-2">{{ ballot.votes_count }}</td>
                                <td class="px-3 py-2">{{ ['scanned', 'flagged'].includes(ballot.status) ? (ballot.scanned_at_formatted ?? '-') : '-' }}</td>
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

        <!-- ── Ballot Generator Modal ──────────────────────────── -->
        <Teleport to="body">
            <div
                v-if="showGeneratorModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                @click.self="showGeneratorModal = false"
            >
                <div class="rounded-2xl bg-white shadow-xl max-w-md w-full overflow-hidden">
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                        <h2 class="text-lg font-semibold text-slate-900">Generate Ballots</h2>
                        <button
                            @click="showGeneratorModal = false"
                            class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors"
                            aria-label="Close modal"
                            type="button"
                        >
                            <i class="bi bi-x text-lg leading-none" aria-hidden="true"></i>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <form @submit.prevent="submitGeneratorForm" class="p-6 space-y-4">
                        <!-- Election Selection -->
                        <div>
                            <label class="block text-sm font-medium mb-2 text-slate-900">Target Election</label>
                            <select
                                v-model="generatorForm.election_id"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">-- Select Election --</option>
                                <option v-for="election in elections" :key="election.id" :value="election.id">
                                    {{ election.label }} ({{ election.status }}, Ballots: {{ election.ballots_count }})
                                </option>
                            </select>
                            <p class="mt-1 text-xs text-slate-500">
                                Choose which election to generate ballots for.
                            </p>
                        </div>

                        <!-- Print Count -->
                        <div>
                            <label class="block text-sm font-medium mb-2 text-slate-900" for="print_count">
                                Printable Ballot Count
                            </label>
                            <input
                                id="print_count"
                                v-model.number="generatorForm.print_count"
                                type="number"
                                min="1"
                                max="5000"
                                required
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            <p class="mt-1 text-xs text-slate-500">
                                Number of NEW ballots to generate and append to the election (1-5000).
                            </p>
                        </div>

                        <!-- Error Messages -->
                        <div v-if="Object.keys(generatorForm.errors).length" class="rounded-lg border border-red-200 bg-red-50 p-3">
                            <ul class="list-disc pl-5 text-xs text-red-800 space-y-1">
                                <li v-for="(error, key) in generatorForm.errors" :key="key">{{ error }}</li>
                            </ul>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex gap-3 pt-4 border-t border-slate-100">
                            <button
                                type="button"
                                @click="showGeneratorModal = false"
                                class="flex-1 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="generatorForm.processing"
                                class="flex-1 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                            >
                                <span v-if="generatorForm.processing">Generating...</span>
                                <span v-else>Generate & Print</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </div>
</template>