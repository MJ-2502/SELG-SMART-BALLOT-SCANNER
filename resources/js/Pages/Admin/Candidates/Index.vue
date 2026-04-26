<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({ candidates: Array, hasElection: Boolean });

const colorPalette = [
    '#EF4444', '#F97316', '#F59E0B', '#EAB308', '#84CC16', '#22C55E',
    '#10B981', '#14B8A6', '#06B6D4', '#0EA5E9', '#3B82F6', '#6366F1',
    '#8B5CF6', '#A855F7', '#D946EF', '#EC4899', '#F43F5E', '#DC2626',
    '#EA580C', '#CA8A04', '#16A34A', '#0891B2', '#2563EB', '#7C3AED',
];

const openColorParty = ref(null);
const colorForm = useForm({ party: '', color_code: '' });

// --- Flash dismiss ---
const showStatus = ref(true);
const showError = ref(true);

const partyGroups = computed(() => {
    const groups = props.candidates.reduce((acc, candidate) => {
        const partyName = candidate.party && String(candidate.party).trim() !== '' ? candidate.party : 'Independent';
        if (!acc[partyName]) acc[partyName] = [];
        acc[partyName].push(candidate);
        return acc;
    }, {});

    return Object.entries(groups)
        .sort(([a], [b]) => a.localeCompare(b))
        .map(([party, candidates]) => ({
            party,
            color_code: candidates.find((c) => c.color_code)?.color_code ?? null,
            candidates: [...candidates].sort((l, r) => {
                const lo = l.position?.display_order ?? 9999;
                const ro = r.position?.display_order ?? 9999;
                return lo !== ro ? lo - ro : String(l.name).localeCompare(String(r.name));
            }),
        }));
});

const confirmDeleteCandidate = () => window.confirm('Delete this candidate? This action cannot be undone.');
const confirmDeletePartylist = () => window.confirm('Delete this partylist and all its candidates? This action cannot be undone.');

const usedColors = computed(() => {
    const colors = props.candidates
        .map((c) => String(c.color_code ?? '').toUpperCase().trim())
        .filter(Boolean);
    return new Set(colors);
});

const toggleColorPicker = (group) => {
    if (openColorParty.value === group.party) {
        openColorParty.value = null;
        colorForm.reset();
        colorForm.clearErrors();
        return;
    }
    openColorParty.value = group.party;
    colorForm.party = group.party;
    colorForm.color_code = String(group.color_code ?? '').toUpperCase();
    colorForm.clearErrors();
};

const isColorUnavailableForGroup = (group, color) => {
    const normalized = String(color).toUpperCase();
    const current = String(group.color_code ?? '').toUpperCase();
    return normalized !== current && usedColors.value.has(normalized);
};

const pickGroupColor = (group, color) => {
    if (!isColorUnavailableForGroup(group, color)) {
        colorForm.color_code = String(color).toUpperCase();
    }
};

const saveGroupColor = () => {
    colorForm.patch('/candidates/partylist/color', {
        preserveScroll: true,
        onSuccess: () => {
            openColorParty.value = null;
            colorForm.reset();
        },
    });
};

// Close color picker on outside click
const handleOutsideClick = (e) => {
    if (openColorParty.value && !e.target.closest('[data-color-picker]')) {
        openColorParty.value = null;
        colorForm.reset();
        colorForm.clearErrors();
    }
};

onMounted(() => document.addEventListener('mousedown', handleOutsideClick));
onUnmounted(() => document.removeEventListener('mousedown', handleOutsideClick));
</script>

<template>
    <Head title="Candidates" />

    <div class="ui-page">

        <!-- Page Header -->
        <div class="ui-card mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-xl font-semibold text-slate-900">Candidates</h1>
                    <p class="text-sm text-slate-500 mt-0.5">Manage candidates and partylists for the current election.</p>
                </div>
                <div class="flex gap-2">
                    <template v-if="hasElection">
                        <Link href="/candidates/create" class="ui-btn-primary">Add Candidate</Link>
                        <Link href="/candidates/partylist/create" class="ui-btn-secondary">Add Partylist</Link>
                    </template>
                    <template v-else>
                        <!-- Rendered as <button disabled> for correct semantics -->
                        <button disabled class="ui-btn-primary opacity-50 cursor-not-allowed">Add Candidate</button>
                        <button disabled class="ui-btn-secondary opacity-50 cursor-not-allowed">Add Partylist</button>
                    </template>
                </div>
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
            <button type="button" class="ml-auto text-emerald-500 hover:text-emerald-700" @click="showStatus = false" aria-label="Dismiss">
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
            <button type="button" class="ml-auto text-rose-400 hover:text-rose-600" @click="showError = false" aria-label="Dismiss">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- No election notice -->
        <div v-if="!hasElection" class="flex items-start gap-3 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 mb-6" role="note">
            <svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <p class="text-sm text-blue-800">
                Create an election first before registering candidates or creating a partylist.
                <Link href="/elections/create" class="font-semibold underline">Create election →</Link>
            </p>
        </div>

        <!-- Party groups -->
        <div
            v-for="group in partyGroups"
            :key="group.party"
            class="ui-card mb-4 overflow-hidden"
            :style="group.color_code ? { borderLeft: `3px solid ${group.color_code}` } : {}"
        >
            <!-- Party header -->
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2.5">
                    <!-- Color dot indicator -->
                    <span
                        class="h-2.5 w-2.5 rounded-full flex-shrink-0"
                        :style="{ backgroundColor: group.color_code || '#CBD5E1' }"
                    ></span>
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">{{ group.party }}</h2>
                        <span class="text-xs text-slate-500">{{ group.candidates.length }} candidate{{ group.candidates.length !== 1 ? 's' : '' }}</span>
                    </div>
                </div>

                <!-- Header actions (color picker + delete partylist) -->
                <div class="relative flex items-center gap-2" data-color-picker>
                    <!-- Color swatch trigger -->
                    <button
                        type="button"
                        class="h-7 w-7 rounded-full border-2 border-slate-300 shadow-sm hover:border-slate-400 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                        :style="{ backgroundColor: group.color_code || '#E5E7EB' }"
                        title="Set partylist colour"
                        aria-label="Set partylist colour"
                        @click="toggleColorPicker(group)"
                    />

                    <!-- Delete partylist – icon-only button (consistent with candidate delete) -->
                    <form
                        v-if="group.party !== 'Independent'"
                        :action="`/candidates/partylist`"
                        method="POST"
                        @submit="(e) => { if (!confirmDeletePartylist()) e.preventDefault(); }"
                    >
                        <input type="hidden" name="_token" :value="$page.props.csrf_token" />
                        <input type="hidden" name="_method" value="DELETE" />
                        <input type="hidden" name="party" :value="group.party" />
                        <button
                            type="submit"
                            class="ui-btn-danger ui-btn-sm"
                            title="Delete partylist"
                            aria-label="Delete partylist"
                        >
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>

                    <!-- Color picker dropdown -->
                    <div
                        v-if="openColorParty === group.party"
                        class="absolute right-0 top-10 z-20 w-72 rounded-xl border border-slate-200 bg-white p-3 shadow-lg"
                    >
                        <p class="text-xs font-semibold text-slate-700 mb-2">Select colour for <span class="text-indigo-600">{{ group.party }}</span></p>
                        <div class="grid grid-cols-8 gap-2 mb-3">
                            <button
                                v-for="color in colorPalette"
                                :key="`${group.party}-${color}`"
                                type="button"
                                class="h-7 w-7 rounded-full border-2 transition"
                                :class="[
                                    colorForm.color_code === color ? 'border-slate-900 scale-110' : 'border-transparent hover:border-slate-400',
                                    isColorUnavailableForGroup(group, color) ? 'opacity-30 cursor-not-allowed' : 'hover:scale-105',
                                ]"
                                :style="{ backgroundColor: color }"
                                :disabled="isColorUnavailableForGroup(group, color)"
                                :title="isColorUnavailableForGroup(group, color) ? `${color} — already used by another partylist` : color"
                                @click="pickGroupColor(group, color)"
                            />
                        </div>

                        <p v-if="colorForm.errors.color_code" class="text-xs text-rose-600 mb-2">{{ colorForm.errors.color_code }}</p>

                        <div class="flex items-center justify-between gap-2 pt-2 border-t border-slate-100">
                            <span class="text-xs text-slate-500 font-mono">{{ colorForm.color_code || 'No colour selected' }}</span>
                            <div class="flex gap-2">
                                <button type="button" class="ui-btn-secondary ui-btn-sm" @click="toggleColorPicker(group)">Cancel</button>
                                <button
                                    type="button"
                                    class="ui-btn-primary ui-btn-sm"
                                    :disabled="!colorForm.color_code || colorForm.processing"
                                    @click="saveGroupColor"
                                >Save</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Candidates table -->
            <div class="overflow-x-auto -mx-4 sm:-mx-6 px-4 sm:px-6">
                <table class="ui-table w-full">
                    <thead>
                        <tr class="ui-row">
                            <th class="ui-th w-14 text-slate-400 font-normal text-xs">ID</th>
                            <th class="ui-th">Name</th>
                            <th class="ui-th">Position</th>
                            <th class="ui-th w-28">Status</th>
                            <th class="ui-th w-24">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Empty state per party (edge case) -->
                        <tr v-if="group.candidates.length === 0">
                            <td colspan="5" class="ui-td text-center text-sm text-slate-400 py-6">No candidates in this partylist yet.</td>
                        </tr>

                        <tr v-for="candidate in group.candidates" :key="candidate.id" class="ui-row group/row hover:bg-slate-50 transition-colors">
                            <td class="ui-td text-xs text-slate-400 tabular-nums">{{ candidate.id }}</td>
                            <td class="ui-td font-medium text-slate-800">{{ candidate.name }}</td>
                            <td class="ui-td text-slate-600">{{ candidate.position?.name }}</td>

                            <!-- Status badge (replaces plain Yes / No) -->
                            <td class="ui-td">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="candidate.is_active
                                        ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200'
                                        : 'bg-slate-100 text-slate-500 ring-1 ring-inset ring-slate-200'"
                                >
                                    <span
                                        class="h-1.5 w-1.5 rounded-full"
                                        :class="candidate.is_active ? 'bg-emerald-500' : 'bg-slate-400'"
                                    ></span>
                                    {{ candidate.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>

                            <!-- Actions -->
                            <td class="ui-td">
                                <div class="flex gap-2">
                                    <Link
                                        :href="`/candidates/${candidate.id}/edit`"
                                        class="ui-btn-secondary ui-btn-sm"
                                        title="Edit candidate"
                                        aria-label="Edit candidate"
                                    >
                                        <i class="bi bi-pen"></i>
                                    </Link>
                                    <form
                                        :action="`/candidates/${candidate.id}`"
                                        method="POST"
                                        @submit="(e) => { if (!confirmDeleteCandidate()) e.preventDefault(); }"
                                    >
                                        <input type="hidden" name="_token" :value="$page.props.csrf_token" />
                                        <input type="hidden" name="_method" value="DELETE" />
                                        <button
                                            type="submit"
                                            class="ui-btn-danger ui-btn-sm"
                                            title="Delete candidate"
                                            aria-label="Delete candidate"
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

        <!-- Empty state (no candidates at all) -->
        <div v-if="partyGroups.length === 0" class="ui-card text-center py-12">
            <svg class="mx-auto h-10 w-10 text-slate-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
            <p class="text-sm font-medium text-slate-600 mb-1">No candidates yet</p>
            <p class="text-xs text-slate-400">Add a candidate or create a partylist to get started.</p>
        </div>

    </div>
</template>