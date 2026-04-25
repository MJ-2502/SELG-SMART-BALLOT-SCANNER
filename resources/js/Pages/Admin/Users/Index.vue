<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

defineProps({ users: Array });

const confirmDelete = () => window.confirm('Delete this facilitator?');
</script>

<template>
    <Head title="Facilitator Credentials" />
    <div class="ui-page">
        <div class="ui-card mb-6">
            <div class="flex justify-between items-center">
                <h1 class="text-xl font-semibold">Facilitator Credentials</h1>
                <Link href="/facilitators/create" class="ui-btn-primary">Add Facilitator</Link>
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
                            <th class="ui-th">Username</th>
                            <th class="ui-th">Grade Level</th>
                            <th class="ui-th">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in users" :key="user.id" class="ui-row">
                            <td class="ui-td">{{ user.id }}</td>
                            <td class="ui-td">{{ user.name }}</td>
                            <td class="ui-td">{{ user.username }}</td>
                            <td class="ui-td">{{ user.grade_level }}</td>
                            <td class="ui-td">
                                <div class="flex gap-2">
                                    <Link :href="`/facilitators/${user.id}/edit`" class="ui-btn-secondary ui-btn-sm" title="Edit facilitator" aria-label="Edit facilitator">
                                        <i class="bi bi-pen"></i>
                                    </Link>
                                    <form :action="`/facilitators/${user.id}`" method="POST" class="inline" @submit="(e) => { if (!confirmDelete()) e.preventDefault(); }">
                                        <input type="hidden" name="_token" :value="$page.props.csrf_token" />
                                        <input type="hidden" name="_method" value="DELETE" />
                                        <button type="submit" class="ui-btn-danger ui-btn-sm" title="Delete facilitator" aria-label="Delete facilitator">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p v-if="users.length === 0" class="text-gray-600 mt-4">No facilitators yet.</p>
        </div>
    </div>
</template>