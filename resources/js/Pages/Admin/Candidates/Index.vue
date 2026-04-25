<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({ candidates: Array, hasElection: Boolean });

const partyGroups = computed(() => {
    const groups = props.candidates.reduce((acc, candidate) => {
        const partyName = candidate.party && String(candidate.party).trim() !== '' ? candidate.party : 'Independent';
        if (!acc[partyName]) {
            acc[partyName] = [];
        }
        acc[partyName].push(candidate);
        return acc;
    }, {});

    return Object.entries(groups)
        .sort(([a], [b]) => a.localeCompare(b))
        .map(([party, candidates]) => ({
            party,
            candidates: [...candidates].sort((left, right) => {
                const leftOrder = left.position?.display_order ?? 9999;
                const rightOrder = right.position?.display_order ?? 9999;
                if (leftOrder !== rightOrder) {
                    return leftOrder - rightOrder;
                }
                return String(left.name).localeCompare(String(right.name));
            }),
        }));
});

const confirmDeleteCandidate = () => window.confirm('Delete this candidate?');
const confirmDeletePartylist = () => window.confirm('Delete this partylist and all its candidates?');
</script>

<template>
    <Head title="Candidates" />
    <div class="ui-page">
        <div class="ui-card mb-6">
            <div class="flex justify-between items-center">
                <h1 class="text-xl font-semibold">Candidates</h1>
                <div class="flex gap-2">
                    <template v-if="hasElection">
                        <Link href="/candidates/create" class="ui-btn-primary">Add Candidate</Link>
                        <Link href="/candidates/partylist/create" class="ui-btn-secondary">Add Partylist</Link>
                    </template>
                    <template v-else>
                        <span class="ui-btn-primary opacity-50 cursor-not-allowed" aria-disabled="true">Add Candidate</span>
                        <span class="ui-btn-secondary opacity-50 cursor-not-allowed" aria-disabled="true">Add Partylist</span>
                    </template>
                </div>
            </div>
        </div>

        <div v-if="$page.props.flash.status" class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
            <p class="text-green-800">{{ $page.props.flash.status }}</p>
        </div>

        <div v-if="$page.props.flash.error" class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <p class="text-red-800">{{ $page.props.flash.error }}</p>
        </div>

        <div v-if="!hasElection" class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <p class="text-blue-800">
                Create an election first before registering candidates or creating a partylist.
                <Link href="/elections/create" class="font-semibold underline">Create election</Link>.
            </p>
        </div>

        <div v-for="group in partyGroups" :key="group.party" class="ui-card mb-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-lg font-semibold">{{ group.party }}</h2>
                    <span class="text-sm text-gray-500">{{ group.candidates.length }} candidate(s)</span>
                </div>

                <form
                    v-if="group.party !== 'Independent'"
                    action="/candidates/partylist"
                    method="POST"
                    @submit="(e) => { if (!confirmDeletePartylist()) e.preventDefault(); }"
                >
                    <input type="hidden" name="_token" :value="$page.props.csrf_token" />
                    <input type="hidden" name="_method" value="DELETE" />
                    <input type="hidden" name="party" :value="group.party" />
                    <button type="submit" class="ui-btn-danger ui-btn-sm">Delete Partylist</button>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="ui-table">
                    <thead>
                        <tr class="ui-row">
                            <th class="ui-th">ID</th>
                            <th class="ui-th">Name</th>
                            <th class="ui-th">Position</th>
                            <th class="ui-th">Active</th>
                            <th class="ui-th">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="candidate in group.candidates" :key="candidate.id" class="ui-row">
                            <td class="ui-td">{{ candidate.id }}</td>
                            <td class="ui-td">{{ candidate.name }}</td>
                            <td class="ui-td">{{ candidate.position?.name }}</td>
                            <td class="ui-td">{{ candidate.is_active ? 'Yes' : 'No' }}</td>
                            <td class="ui-td">
                                <div class="flex gap-2">
                                    <Link :href="`/candidates/${candidate.id}/edit`" class="ui-btn-secondary ui-btn-sm" title="Edit candidate" aria-label="Edit candidate">
                                        <i class="bi bi-pen"></i>
                                    </Link>
                                    <form :action="`/candidates/${candidate.id}`" method="POST" @submit="(e) => { if (!confirmDeleteCandidate()) e.preventDefault(); }">
                                        <input type="hidden" name="_token" :value="$page.props.csrf_token" />
                                        <input type="hidden" name="_method" value="DELETE" />
                                        <button type="submit" class="ui-btn-danger ui-btn-sm" title="Delete candidate" aria-label="Delete candidate">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="partyGroups.length === 0" class="ui-card">
            <p class="text-gray-600">No candidates yet.</p>
        </div>
    </div>
</template>