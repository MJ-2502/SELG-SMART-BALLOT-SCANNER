<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

defineProps({ positions: Array });

const showStatus = ref(true);

const confirmDelete = () => window.confirm('Delete this position? This action cannot be undone.');
</script>

<template>
    <Head title="Officer Positions" />

    <div class="ui-page">

        <!-- Page Header -->
        <div class="ui-card mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-xl font-semibold text-slate-900">Officer Positions</h1>
                    <p class="text-sm text-slate-500 mt-0.5">Define the positions candidates can run for and how many votes each allows.</p>
                </div>
                <Link href="/positions/create" class="ui-btn-primary">Add Position</Link>
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

        <!-- Positions table -->
        <div class="ui-card overflow-hidden">
            <div class="overflow-x-auto -mx-4 sm:-mx-6 px-4 sm:px-6">
                <table class="ui-table w-full">
                    <thead>
                        <tr class="ui-row">
                            <th class="ui-th w-14 text-slate-400 font-normal text-xs">ID</th>
                            <th class="ui-th">Name</th>
                            <th class="ui-th w-28">Order</th>
                            <th class="ui-th w-36">Votes Allowed</th>
                            <th class="ui-th w-24">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Empty state -->
                        <tr v-if="positions.length === 0">
                            <td colspan="5" class="py-12 text-center">
                                <svg class="mx-auto h-10 w-10 text-slate-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h7.5M8.25 12h7.5m-7.5 5.25h3.75M3.75 3h16.5A.75.75 0 0121 3.75v16.5a.75.75 0 01-.75.75H3.75A.75.75 0 013 20.25V3.75A.75.75 0 013.75 3z"/></svg>
                                <p class="text-sm font-medium text-slate-600 mb-1">No positions yet</p>
                                <p class="text-xs text-slate-400">Add a position to define what candidates can run for.</p>
                            </td>
                        </tr>

                        <tr
                            v-for="position in positions"
                            :key="position.id"
                            class="ui-row hover:bg-slate-50 transition-colors"
                        >
                            <!-- ID: de-emphasised -->
                            <td class="ui-td text-xs text-slate-400 tabular-nums">{{ position.id }}</td>

                            <!-- Name: primary content -->
                            <td class="ui-td font-medium text-slate-800">{{ position.name }}</td>

                            <!-- Display order: badge so it reads as a rank, not a plain number -->
                            <td class="ui-td">
                                <span class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-200">
                                    <svg class="h-3 w-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h18M3 12h18M3 17h18"/></svg>
                                    {{ position.display_order }}
                                </span>
                            </td>

                            <!-- Votes allowed: indigo badge for plurality positions -->
                            <td class="ui-td">
                                <span
                                    class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
                                    :class="position.votes_allowed > 1
                                        ? 'bg-indigo-50 text-indigo-700 ring-indigo-200'
                                        : 'bg-slate-100 text-slate-600 ring-slate-200'"
                                >
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    {{ position.votes_allowed }} {{ position.votes_allowed === 1 ? 'vote' : 'votes' }}
                                </span>
                            </td>

                            <!-- Actions -->
                            <td class="ui-td">
                                <div class="flex gap-2">
                                    <Link
                                        :href="`/positions/${position.id}/edit`"
                                        class="ui-btn-secondary ui-btn-sm"
                                        title="Edit position"
                                        aria-label="Edit position"
                                    >
                                        <i class="bi bi-pen"></i>
                                    </Link>
                                    <form
                                        :action="`/positions/${position.id}`"
                                        method="POST"
                                        class="inline"
                                        @submit="(e) => { if (!confirmDelete()) e.preventDefault(); }"
                                    >
                                        <input type="hidden" name="_token" :value="$page.props.csrf_token" />
                                        <input type="hidden" name="_method" value="DELETE" />
                                        <button
                                            type="submit"
                                            class="ui-btn-danger ui-btn-sm"
                                            title="Delete position"
                                            aria-label="Delete position"
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