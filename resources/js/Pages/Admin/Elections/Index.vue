<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({ elections: Array, facilitators: Array });

const showStatus = ref(true);
const showError = ref(true);

// Track which election's facilitator dropdown is open
const openDropdownId = ref(null);

const toggleDropdown = (electionId) => {
    openDropdownId.value = openDropdownId.value === electionId ? null : electionId;
};

const handleOutsideClick = (e) => {
    if (openDropdownId.value && !e.target.closest('[data-facilitator-dropdown]')) {
        openDropdownId.value = null;
    }
};

onMounted(() => document.addEventListener('mousedown', handleOutsideClick));
onUnmounted(() => document.removeEventListener('mousedown', handleOutsideClick));

const isAssigned = (election, facilitatorId) =>
    election.facilitators?.some((f) => Number(f.id) === Number(facilitatorId)) ?? false;

const facilitatorLabel = (election) => {
    if (!election.facilitators?.length) return null;
    return election.facilitators.map((f) => f.name).join(', ');
};

// Confirmation dialogs — name the specific election so the user knows what they're doing
const confirmStart = (name) =>
    window.confirm(`Start the election "${name}"?\n\nOnce started, ballot scanning will go live.`);

const confirmStop = (name) =>
    window.confirm(`Stop the election "${name}"?\n\nThis will end ballot scanning. You can't restart it afterwards.`);

const confirmDelete = (name) =>
    window.confirm(`Delete "${name}"?\n\nThis will permanently remove the election and all its ballot data. This cannot be undone.`);
</script>

<template>
    <Head title="Manage Elections" />

    <div class="ui-page">

        <!-- Page Header -->
        <div class="ui-card mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-xl font-semibold text-slate-900">Manage Elections</h1>
                    <p class="text-sm text-slate-500 mt-0.5">Create and oversee elections, assign facilitators, and control election status.</p>
                </div>
                <Link href="/elections/create" class="ui-btn-primary">Create Election</Link>
            </div>
        </div>

        <!-- Flash: success -->
        <div
            v-if="$page.props.flash.status && showStatus"
            class="flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 mb-4"
            role="alert"
        >
            <svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            <p class="text-sm text-emerald-800 flex-1">{{ $page.props.flash.status }}</p>
            <button type="button" class="text-emerald-500 hover:text-emerald-700" @click="showStatus = false" aria-label="Dismiss">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- Flash: error -->
        <div
            v-if="$page.props.flash.error && showError"
            class="flex items-start gap-3 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 mb-4"
            role="alert"
        >
            <svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <p class="text-sm text-rose-800 flex-1">{{ $page.props.flash.error }}</p>
            <button type="button" class="text-rose-400 hover:text-rose-600" @click="showError = false" aria-label="Dismiss">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- Elections table -->
        <div class="ui-card overflow-hidden">
            <div class="overflow-x-auto -mx-4 sm:-mx-6 px-4 sm:px-6">
                <table class="ui-table w-full">
                    <thead>
                        <tr class="ui-row">
                            <th class="ui-th">Election Name</th>
                            <th class="ui-th w-52">Election Date</th>
                            <th class="ui-th w-64">Facilitator</th>
                            <th class="ui-th w-28">Status</th>
                            <th class="ui-th w-32">Ballots</th>
                            <th class="ui-th w-28">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Empty state -->
                        <tr v-if="elections.length === 0">
                            <td colspan="6" class="py-12 text-center">
                                <svg class="mx-auto h-10 w-10 text-slate-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V19.5a2.25 2.25 0 002.25 2.25h.75m0-3.75h3.75m0-3.75h-3.75"/></svg>
                                <p class="text-sm font-medium text-slate-600 mb-1">No elections yet</p>
                                <p class="text-xs text-slate-400 mb-3">Create your first election to get started.</p>
                                <Link href="/elections/create" class="ui-btn-primary ui-btn-sm">Create Election</Link>
                            </td>
                        </tr>

                        <tr
                            v-for="election in elections"
                            :key="election.id"
                            class="ui-row hover:bg-slate-50 transition-colors"
                        >
                            <!-- Election name -->
                            <td class="ui-td font-medium text-slate-800">{{ election.election_name }}</td>

                            <!-- Date -->
                            <td class="ui-td text-slate-600 tabular-nums">
                                {{ election.election_date_formatted ?? election.election_date }}
                            </td>

                            <!-- Facilitator + assign dropdown -->
                            <td class="ui-td">
                                <!-- Assigned names or "unassigned" hint -->
                                <p class="text-sm text-slate-700 mb-1.5">
                                    <span v-if="facilitatorLabel(election)">{{ facilitatorLabel(election) }}</span>
                                    <span v-else class="text-slate-400 italic text-xs">No facilitators assigned</span>
                                </p>

                                <!-- Assign dropdown trigger -->
                                <div class="relative inline-block" data-facilitator-dropdown>
                                    <button
                                        type="button"
                                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-600 shadow-sm hover:bg-slate-50 transition"
                                        @click="toggleDropdown(election.id)"
                                    >
                                        <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                                        Assign Facilitator
                                        <i class="bi bi-chevron-down text-xs leading-none"></i>
                                    </button>

                                    <!-- Dropdown panel -->
                                    <div
                                        v-if="openDropdownId === election.id"
                                        class="absolute left-0 top-full z-30 mt-2 w-80 max-w-[90vw] rounded-xl border border-slate-200 bg-white shadow-lg"
                                    >
                                        <form :action="`/elections/${election.id}/facilitators`" method="POST">
                                            <input type="hidden" name="_token" :value="$page.props.csrf_token" />
                                            <input type="hidden" name="_method" value="PATCH" />

                                            <div class="max-h-64 overflow-y-auto">
                                                <div v-if="facilitators.length === 0" class="px-4 py-6 text-center text-sm text-slate-500">
                                                    No facilitators found.
                                                    <Link href="/facilitators/create" class="block mt-1 text-indigo-600 font-semibold">Add one →</Link>
                                                </div>

                                                <label
                                                    v-for="facilitator in facilitators"
                                                    :key="facilitator.id"
                                                    class="flex cursor-pointer items-center gap-3 border-b border-slate-100 px-4 py-2.5 transition hover:bg-indigo-50 last:border-b-0"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        name="facilitator_ids[]"
                                                        :value="facilitator.id"
                                                        :checked="isAssigned(election, facilitator.id)"
                                                        class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                                    />

                                                    <!-- Avatar with initials -->
                                                    <span class="inline-flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700">
                                                        {{ facilitator.name?.charAt(0)?.toUpperCase() ?? '?' }}
                                                    </span>

                                                    <span class="flex-1 min-w-0">
                                                        <span class="block text-sm font-medium text-slate-900 truncate">{{ facilitator.name }}</span>
                                                        <span class="block text-xs text-slate-500 font-mono">@{{ facilitator.username }} · Grade {{ facilitator.grade_level }}</span>
                                                    </span>
                                                </label>
                                            </div>

                                            <div class="border-t border-slate-100 flex items-center justify-between bg-slate-50 px-4 py-2.5 rounded-b-xl">
                                                <button
                                                    type="button"
                                                    class="text-xs text-slate-500 hover:text-slate-700"
                                                    @click="openDropdownId = null"
                                                >
                                                    Cancel
                                                </button>
                                                <button type="submit" class="ui-btn-primary ui-btn-sm">
                                                    Save assignments
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </td>

                            <!-- Status badge -->
                            <td class="ui-td">
                                <span
                                    v-if="election.status === 'active'"
                                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                    Active
                                </span>
                                <span
                                    v-else-if="election.status === 'completed'"
                                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-200"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                                    Completed
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full bg-amber-400"></span>
                                    Pending
                                </span>
                            </td>

                            <!-- Ballot count badge -->
                            <td class="ui-td">
                                <span
                                    v-if="(election.ballots_count ?? 0) > 0"
                                    class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200"
                                >
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    {{ (election.ballots_count ?? 0).toLocaleString() }} ballots
                                </span>
                                <span v-else class="text-xs text-slate-400 italic">No ballots yet</span>
                            </td>

                            <!-- Actions
                                 Start  → green  (constructive)
                                 Stop   → amber  (caution — distinct from destructive delete)
                                 Delete → red    (destructive)
                            -->
                            <td class="ui-td">
                                <div class="flex gap-2">
                                    <!-- Start: only when pending -->
                                    <form
                                        v-if="election.status === 'pending'"
                                        :action="`/elections/${election.id}/start`"
                                        method="POST"
                                        class="inline"
                                        @submit="(e) => { if (!confirmStart(election.election_name)) e.preventDefault(); }"
                                    >
                                        <input type="hidden" name="_token" :value="$page.props.csrf_token" />
                                        <button
                                            type="submit"
                                            class="ui-btn-sm inline-flex items-center justify-center rounded-md border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-emerald-700 hover:bg-emerald-100 transition"
                                            title="Start election"
                                            aria-label="Start election"
                                        >
                                            <i class="bi bi-play-fill"></i>
                                        </button>
                                    </form>

                                    <!-- Stop: only when active — amber so it doesn't look like Delete -->
                                    <form
                                        v-if="election.status === 'active'"
                                        :action="`/elections/${election.id}/stop`"
                                        method="POST"
                                        class="inline"
                                        @submit="(e) => { if (!confirmStop(election.election_name)) e.preventDefault(); }"
                                    >
                                        <input type="hidden" name="_token" :value="$page.props.csrf_token" />
                                        <button
                                            type="submit"
                                            class="ui-btn-sm inline-flex items-center justify-center rounded-md border border-amber-200 bg-amber-50 px-2.5 py-1.5 text-amber-700 hover:bg-amber-100 transition"
                                            title="Stop election"
                                            aria-label="Stop election"
                                        >
                                            <i class="bi bi-stop-fill"></i>
                                        </button>
                                    </form>

                                    <!-- Delete: only when not active -->
                                    <form
                                        v-if="election.status !== 'active'"
                                        :action="`/elections/${election.id}`"
                                        method="POST"
                                        class="inline"
                                        @submit="(e) => { if (!confirmDelete(election.election_name)) e.preventDefault(); }"
                                    >
                                        <input type="hidden" name="_token" :value="$page.props.csrf_token" />
                                        <input type="hidden" name="_method" value="DELETE" />
                                        <button
                                            type="submit"
                                            class="ui-btn-danger ui-btn-sm"
                                            title="Delete election"
                                            aria-label="Delete election"
                                        >
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