<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

defineProps({ users: Array });

const showStatus = ref(true);

const confirmDelete = () => window.confirm('Delete this facilitator? This action cannot be undone.');
</script>

<template>
    <Head title="Facilitator Credentials" />

    <div class="ui-page">

        <!-- Page Header -->
        <div class="ui-card mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-xl font-semibold text-slate-900">Facilitator Credentials</h1>
                    <p class="text-sm text-slate-500 mt-0.5">Manage the accounts used by facilitators to operate ballot scanners.</p>
                </div>
                <Link href="/facilitators/create" class="ui-btn-primary">Add Facilitator</Link>
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

        <!-- Facilitators table -->
        <div class="ui-card overflow-hidden">
            <div class="overflow-x-auto -mx-4 sm:-mx-6 px-4 sm:px-6">
                <table class="ui-table w-full">
                    <thead>
                        <tr class="ui-row">
                            <th class="ui-th w-14 text-slate-400 font-normal text-xs">ID</th>
                            <th class="ui-th">Name</th>
                            <th class="ui-th">Username</th>
                            <th class="ui-th w-36">Grade Level</th>
                            <th class="ui-th w-24">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Empty state -->
                        <tr v-if="users.length === 0">
                            <td colspan="5" class="py-12 text-center">
                                <svg class="mx-auto h-10 w-10 text-slate-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/></svg>
                                <p class="text-sm font-medium text-slate-600 mb-1">No facilitators yet</p>
                                <p class="text-xs text-slate-400">Add a facilitator account to allow scanner operation.</p>
                            </td>
                        </tr>

                        <tr
                            v-for="user in users"
                            :key="user.id"
                            class="ui-row hover:bg-slate-50 transition-colors"
                        >
                            <!-- ID: de-emphasised -->
                            <td class="ui-td text-xs text-slate-400 tabular-nums">{{ user.id }}</td>

                            <!-- Name with avatar initials -->
                            <td class="ui-td">
                                <div class="flex items-center gap-2.5">
                                    <span class="inline-flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700">
                                        {{ user.name?.charAt(0)?.toUpperCase() ?? '?' }}
                                    </span>
                                    <span class="font-medium text-slate-800">{{ user.name }}</span>
                                </div>
                            </td>

                            <!-- Username: monospace for credential readability -->
                            <td class="ui-td">
                                <span class="inline-flex items-center gap-1.5 rounded-md bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-700 ring-1 ring-inset ring-slate-200">
                                    <svg class="h-3 w-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                                    {{ user.username }}
                                </span>
                            </td>

                            <!-- Grade level badge -->
                            <td class="ui-td">
                                <span class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-200">
                                    Grade {{ user.grade_level }}
                                </span>
                            </td>

                            <!-- Actions -->
                            <td class="ui-td">
                                <div class="flex gap-2">
                                    <Link
                                        :href="`/facilitators/${user.id}/edit`"
                                        class="ui-btn-secondary ui-btn-sm"
                                        title="Edit facilitator"
                                        aria-label="Edit facilitator"
                                    >
                                        <i class="bi bi-pen"></i>
                                    </Link>
                                    <form
                                        :action="`/facilitators/${user.id}`"
                                        method="POST"
                                        class="inline"
                                        @submit="(e) => { if (!confirmDelete()) e.preventDefault(); }"
                                    >
                                        <input type="hidden" name="_token" :value="$page.props.csrf_token" />
                                        <input type="hidden" name="_method" value="DELETE" />
                                        <button
                                            type="submit"
                                            class="ui-btn-danger ui-btn-sm"
                                            title="Delete facilitator"
                                            aria-label="Delete facilitator"
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