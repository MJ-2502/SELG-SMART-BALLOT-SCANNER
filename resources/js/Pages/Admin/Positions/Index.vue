<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

defineProps({ positions: Array });

const confirmDelete = () => window.confirm('Delete this position?');
</script>

<template>
    <Head title="Officer Positions" />
    <div class="ui-page">
        <div class="ui-card mb-6">
            <div class="flex justify-between items-center">
                <h1 class="text-xl font-semibold">Officer Positions</h1>
                <Link href="/positions/create" class="ui-btn-primary">Add Position</Link>
            </div>
        </div>

        <div v-if="$page.props.flash.status" class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
            <p class="text-green-800">{{ $page.props.flash.status }}</p>
        </div>

        <div class="ui-card">
            <div class="overflow-x-auto">
                <table class="ui-table">
                    <thead>
                        <tr class="ui-row">
                            <th class="ui-th">ID</th>
                            <th class="ui-th">Name</th>
                            <th class="ui-th">Order</th>
                            <th class="ui-th">Votes Allowed</th>
                            <th class="ui-th">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="position in positions" :key="position.id" class="ui-row">
                            <td class="ui-td">{{ position.id }}</td>
                            <td class="ui-td">{{ position.name }}</td>
                            <td class="ui-td">{{ position.display_order }}</td>
                            <td class="ui-td">{{ position.votes_allowed }}</td>
                            <td class="ui-td">
                                <div class="flex gap-2">
                                    <Link :href="`/positions/${position.id}/edit`" class="ui-btn-secondary ui-btn-sm" title="Edit position" aria-label="Edit position">
                                        <i class="bi bi-pen"></i>
                                    </Link>
                                    <form :action="`/positions/${position.id}`" method="POST" class="inline" @submit="(e) => { if (!confirmDelete()) e.preventDefault(); }">
                                        <input type="hidden" name="_token" :value="$page.props.csrf_token" />
                                        <input type="hidden" name="_method" value="DELETE" />
                                        <button type="submit" class="ui-btn-danger ui-btn-sm" title="Delete position" aria-label="Delete position">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p v-if="positions.length === 0" class="text-gray-600 mt-4">No positions yet.</p>
        </div>
    </div>
</template>