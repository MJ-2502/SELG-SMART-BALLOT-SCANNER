<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({ elections: Array, facilitators: Array });

const confirmDelete = () => window.confirm('Are you sure?');

const isAssigned = (election, facilitatorId) => {
    if (!election.facilitators || election.facilitators.length === 0) {
        return false;
    }

    return election.facilitators.some((facilitator) => Number(facilitator.id) === Number(facilitatorId));
};

const facilitatorLabel = (election) => {
    if (!election.facilitators || election.facilitators.length === 0) {
        return 'Unassigned';
    }

    return election.facilitators.map((facilitator) => facilitator.name).join(', ');
};
</script>

<template>
    <Head title="Manage Elections" />
    <div class="ui-page">
        <div class="ui-card min-h-screen flex flex-col">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-xl font-semibold">Manage Elections</h1>
                <Link href="/elections/create" class="ui-btn-primary">Create Election</Link>
            </div>

            <div v-if="$page.props.flash.status" class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                <p class="text-green-800">{{ $page.props.flash.status }}</p>
            </div>

            <div v-if="$page.props.flash.error" class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                <p class="text-red-800">{{ $page.props.flash.error }}</p>
            </div>

            <div v-if="props.elections.length === 0" class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <p class="text-blue-800">No elections found. <Link href="/elections/create" class="font-semibold">Create one</Link>.</p>
            </div>

            <div v-else class="overflow-x-auto max-h-screen border rounded-lg shadow-sm flex-1 flex flex-col">
                <table class="w-full text-sm">
                    <thead class="bg-gray-100 border-b sticky top-0 z-10">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">Election Name</th>
                            <th class="px-4 py-3 text-left font-medium">Election Date</th>
                            <th class="px-4 py-3 text-left font-medium">Facilitator</th>
                            <th class="px-4 py-3 text-left font-medium">Status</th>
                            <th class="px-4 py-3 text-left font-medium">Ballots</th>
                            <th class="px-4 py-3 text-left font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="election in props.elections" :key="election.id" class="border-b hover:bg-gray-50">
                            <td class="px-4 py-3">{{ election.election_name }}</td>
                            <td class="px-4 py-3">{{ election.election_date_formatted ?? election.election_date }}</td>
                            <td class="px-4 py-3">
                                <div class="text-sm font-medium text-slate-900">
                                    {{ facilitatorLabel(election) }}
                                </div>

                                <details class="mt-2 group relative inline-block">
                                    <summary class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                                        Assign Facilitator
                                        <i class="bi bi-chevron-down text-xs leading-none"></i>
                                    </summary>

                                    <form :action="`/elections/${election.id}/facilitators`" method="POST" class="absolute left-0 top-full z-30 mt-2 w-80 max-w-[90vw] rounded-lg border border-slate-200 bg-white shadow-lg">
                                        <input type="hidden" name="_token" :value="$page.props.csrf_token" />
                                        <input type="hidden" name="_method" value="PATCH" />

                                        <div class="max-h-64 overflow-y-auto">
                                            <div v-if="facilitators.length === 0" class="px-4 py-6 text-center text-sm text-slate-500">
                                                No facilitators found.
                                            </div>

                                            <label
                                                v-for="facilitator in facilitators"
                                                :key="facilitator.id"
                                                class="flex cursor-pointer items-center gap-3 border-b border-slate-100 px-4 py-3 transition hover:bg-indigo-50"
                                            >
                                                <input
                                                    type="checkbox"
                                                    name="facilitator_ids[]"
                                                    :value="facilitator.id"
                                                    :checked="isAssigned(election, facilitator.id)"
                                                    class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                                >
                                                <span class="flex-1">
                                                    <span class="block text-sm font-medium text-slate-900">{{ facilitator.name }}</span>
                                                    <span class="block text-xs text-slate-500">@{{ facilitator.username }} | Grade {{ facilitator.grade_level }}</span>
                                                </span>
                                            </label>
                                        </div>

                                        <div class="border-t border-slate-200 flex items-center justify-end bg-slate-50 px-4 py-3">
                                            <button type="submit" class="rounded bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800">
                                                Save Assignments
                                            </button>
                                        </div>
                                    </form>
                                </details>
                            </td>
                            <td class="px-4 py-3">
                                <span v-if="election.status === 'active'" class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">Active</span>
                                <span v-else-if="election.status === 'completed'" class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-800">Completed</span>
                                <span v-else class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">Pending</span>
                            </td>
                            <td class="px-4 py-3">{{ election.ballots_count ?? 0 }}</td>
                            <td class="px-4 py-3">
                                <div class="flex gap-2 flex-wrap">
                                    <form v-if="election.status === 'pending'" :action="`/elections/${election.id}/start`" method="POST" style="display:inline;">
                                        <input type="hidden" name="_token" :value="$page.props.csrf_token" />
                                        <button type="submit" class="inline-block px-3 py-1 rounded text-xs font-semibold bg-green-500 text-white hover:bg-green-600" title="Start election" aria-label="Start election">
                                            <i class="bi bi-play-fill"></i>
                                        </button>
                                    </form>

                                    <form v-if="election.status === 'active'" :action="`/elections/${election.id}/stop`" method="POST" style="display:inline;">
                                        <input type="hidden" name="_token" :value="$page.props.csrf_token" />
                                        <button type="submit" class="inline-block px-3 py-1 rounded text-xs font-semibold bg-red-500 text-white hover:bg-red-600" title="Stop election" aria-label="Stop election">
                                            <i class="bi bi-stop-fill"></i>
                                        </button>
                                    </form>

                                    <form v-if="election.status !== 'active'" :action="`/elections/${election.id}`" method="POST" style="display:inline;" @submit="(e) => { if (!confirmDelete()) e.preventDefault(); }">
                                        <input type="hidden" name="_token" :value="$page.props.csrf_token" />
                                        <input type="hidden" name="_method" value="DELETE" />
                                        <button type="submit" class="inline-block px-3 py-1 rounded text-xs font-semibold bg-red-500 text-white hover:bg-red-600" title="Delete election" aria-label="Delete election">
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
    </div>
</template>