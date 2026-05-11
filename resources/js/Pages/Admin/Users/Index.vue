<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

defineProps({ users: Array });

const showStatus = ref(true);

// Password visibility toggle
const visiblePasswordIds = ref({});

// Modal state management
const isModalOpen = ref(false);
const selectedUser = ref(null);

const togglePasswordVisibility = (userId) => {
    const nextVisibility = {};

    if (!visiblePasswordIds.value[userId]) {
        nextVisibility[userId] = true;
    }

    visiblePasswordIds.value = nextVisibility;
};

const openDetails = (user) => {
    selectedUser.value = user;
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    // Small timeout to allow the fade-out transition before wiping data
    setTimeout(() => {
        selectedUser.value = null;
    }, 200);
};

const confirmDelete = () => window.confirm('Delete this facilitator? This action cannot be undone.');
</script>

<template>
    <Head title="Facilitator Credentials" />

    <div class="ui-page">

        <div class="ui-card mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-xl font-semibold text-slate-900">Facilitator Credentials</h1>
                    <p class="text-sm text-slate-500 mt-0.5">Manage the accounts used by facilitators to operate ballot scanners.</p>
                </div>
                <Link href="/facilitators/create" class="ui-btn-primary">Add Facilitator</Link>
            </div>
        </div>

        <div
            v-if="$page.props.flash?.status && showStatus"
            class="flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 mb-4"
            role="alert"
        >
            <div class="text-emerald-800 text-sm font-medium">{{ $page.props.flash.status }}</div>
            <button @click="showStatus = false" class="ml-auto text-emerald-600 hover:text-emerald-800">&times;</button>
        </div>

        <div class="ui-card p-0 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-50 text-slate-700 font-semibold border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-4">Facilitator Name</th>
                            <th class="px-6 py-4">Username</th>
                            <th class="px-6 py-4">Password</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <tr v-for="user in users" :key="user.id" class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4 font-medium text-slate-900">{{ user.name }}</td>
                            <td class="px-6 py-4">{{ user.username }}</td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <span :class="[
                                        'font-mono text-sm',
                                        visiblePasswordIds[user.id] ? 'text-slate-900' : 'text-slate-400 italic'
                                    ]">
                                        {{ visiblePasswordIds[user.id] ? user.plain_password : '**************' }}
                                    </span>
                                    <button
                                        @click="togglePasswordVisibility(user.id)"
                                        :title="visiblePasswordIds[user.id] ? 'Hide password' : 'Show password'"
                                        class="text-slate-400 hover:text-slate-600 transition-colors"
                                    >
                                        <i :class="['bi', visiblePasswordIds[user.id] ? 'bi-eye-slash' : 'bi-eye']"></i>
                                    </button>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end items-center gap-2">
                                    
                                    <button
                                        @click="openDetails(user)"
                                        class="ui-btn-secondary ui-btn-sm !px-2.5"
                                        title="View details"
                                    >
                                        <i>Details</i>
                                    </button>

                                    <Link
                                        :href="`/facilitators/${user.id}/edit`"
                                        class="ui-btn-secondary ui-btn-sm"
                                        title="Edit facilitator"
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
                                        >
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>

                                </div>
                            </td>
                        </tr>
                        <tr v-if="!users || users.length === 0">
                            <td colspan="4" class="px-6 py-8 text-center text-slate-500">
                                No facilitators found. Click "Add Facilitator" to create one.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="isModalOpen" class="relative z-50" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div 
                class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity" 
                @click="closeModal"
            ></div>

            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <div 
                        class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-md"
                        @click.stop
                    >
                        <div class="bg-white px-6 pb-6 pt-6 text-left">
                            <div class="flex items-center justify-between mb-5">
                                <h3 class="text-lg font-bold text-slate-900" id="modal-title">Facilitator Details</h3>
                                <button @click="closeModal" class="text-slate-400 hover:text-slate-600">
                                    <i class="bi bi-x-lg text-lg"></i>
                                </button>
                            </div>
                            
                            <div class="space-y-4 text-sm text-slate-600" v-if="selectedUser">
                                <div class="grid grid-cols-3 gap-4 border-b border-slate-100 pb-3">
                                    <span class="font-medium text-slate-400 uppercase tracking-wider text-xs">Full Name</span>
                                    <span class="col-span-2 text-slate-900 font-semibold">{{ selectedUser.name }}</span>
                                </div>
                                <div class="grid grid-cols-3 gap-4 border-b border-slate-100 pb-3">
                                    <span class="font-medium text-slate-400 uppercase tracking-wider text-xs">Gender</span>
                                    <span class="col-span-2 text-slate-800">{{ selectedUser.gender || 'N/A' }}</span>
                                </div>
                                <div class="grid grid-cols-3 gap-4 border-b border-slate-100 pb-3">
                                    <span class="font-medium text-slate-400 uppercase tracking-wider text-xs">Grade Level</span>
                                    <span class="col-span-2 text-slate-800">{{ selectedUser.grade_level || 'N/A' }}</span>
                                </div>
                                <div class="grid grid-cols-3 gap-4 border-b border-slate-100 pb-3">
                                    <span class="font-medium text-slate-400 uppercase tracking-wider text-xs">Section</span>
                                    <span class="col-span-2 text-slate-800">{{ selectedUser.section || 'N/A' }}</span>
                                </div>
                                <div class="grid grid-cols-3 gap-4 pb-1">
                                    <span class="font-medium text-slate-400 uppercase tracking-wider text-xs">Username</span>
                                    <span class="col-span-2 text-slate-800">{{ selectedUser.username }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div>
</template>