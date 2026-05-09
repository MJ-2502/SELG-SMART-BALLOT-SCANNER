<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({ positions: Array });

const showStatus = ref(true);

// --- Modal State ---
const isModalOpen = ref(false);
const modalMode = ref('create'); // 'create' or 'edit'
const currentPositionId = ref(null);

// --- Shared Form ---
const form = useForm({
    name: '',
    display_order: 0,
    votes_allowed: 1,
    max_candidates_per_party: 1
});

// Auto-calculate the next logical display order for new creations
const nextOrder = computed(() => {
    if (!props.positions || props.positions.length === 0) return 1;
    return Math.max(...props.positions.map(p => p.display_order)) + 1;
});

// --- Modal Actions ---
const openCreateModal = () => {
    modalMode.value = 'create';
    currentPositionId.value = null;
    form.clearErrors();
    form.name = '';
    form.display_order = nextOrder.value;
    form.votes_allowed = 1;
    form.max_candidates_per_party = 1;
    isModalOpen.value = true;
};

const openEditModal = (position) => {
    modalMode.value = 'edit';
    currentPositionId.value = position.id;
    form.clearErrors();
    form.name = position.name ?? '';
    form.display_order = position.display_order ?? 0;
    form.votes_allowed = position.votes_allowed ?? 1;
    form.max_candidates_per_party = position.max_candidates_per_party ?? 1;
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    form.reset();
    form.clearErrors();
};

const submitForm = () => {
    if (modalMode.value === 'create') {
        form.post('/positions', {
            onSuccess: () => closeModal(),
        });
    } else {
        // Inertia uses _method: 'PATCH' for updating via forms
        form.transform((data) => ({ ...data, _method: 'PATCH' }))
            .post(`/positions/${currentPositionId.value}`, {
                onSuccess: () => closeModal(),
            });
    }
};

const confirmDelete = () => window.confirm('Delete this position? This action cannot be undone.');
</script>

<template>
    <Head title="Officer Positions" />

    <div class="ui-page">
        <div class="ui-card mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-xl font-semibold text-slate-900">Officer Positions</h1>
                    <p class="text-sm text-slate-500 mt-0.5">Define the positions candidates can run for and how many votes each allows.</p>
                </div>
                <button @click="openCreateModal" class="ui-btn-primary">Add Position</button>
            </div>
        </div>

        <div v-if="$page.props.flash.status && showStatus" class="flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 mb-4" role="alert">
            <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div class="flex-1 text-sm font-medium text-emerald-800">{{ $page.props.flash.status }}</div>
            <button @click="showStatus = false" class="text-emerald-500 hover:text-emerald-700">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div class="ui-card p-0 overflow-x-auto">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-slate-50 text-slate-600 border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-3 font-semibold">Order</th>
                        <th class="px-6 py-3 font-semibold">Position Name</th>
                        <th class="px-6 py-3 font-semibold text-center">Votes Allowed</th>
                        <th class="px-6 py-3 font-semibold text-center">Max Per Party</th>
                        <th class="px-6 py-3 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-if="!positions || positions.length === 0">
                        <td colspan="5" class="px-6 py-8 text-center text-slate-500">No positions found. Create one to get started!</td>
                    </tr>
                    <tr v-for="position in positions" :key="position.id" class="hover:bg-slate-50/50 transition">
                        <td class="px-6 py-4 text-slate-500">{{ position.display_order }}</td>
                        <td class="px-6 py-4 font-medium text-slate-900">{{ position.name }}</td>
                        <td class="px-6 py-4 text-center text-slate-700">{{ position.votes_allowed }}</td>
                        <td class="px-6 py-4 text-center text-slate-700">{{ position.max_candidates_per_party || 1 }}</td>
                        <td class="px-6 py-4 text-right space-x-2">
                            <button @click="openEditModal(position)" class="ui-btn-secondary ui-btn-sm" title="Edit position">
                                <i class="bi bi-pen"></i>
                            </button>
                            <form :action="`/positions/${position.id}`" method="POST" class="inline" @submit="(e) => { if (!confirmDelete()) e.preventDefault(); }">
                                <input type="hidden" name="_token" :value="$page.props.csrf_token" />
                                <input type="hidden" name="_method" value="DELETE" />
                                <button type="submit" class="ui-btn-danger ui-btn-sm" title="Delete position">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div v-if="isModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm px-4 transition-opacity">
        <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl relative max-h-[90vh] overflow-y-auto">
            
            <div class="flex justify-between items-center mb-5 border-b pb-3">
                <h2 class="text-xl font-semibold text-slate-800">
                    {{ modalMode === 'create' ? 'Add Position' : 'Edit Position' }}
                </h2>
                <button @click="closeModal" class="text-slate-400 hover:text-slate-600 transition">
                    <i class="bi bi-x-lg text-lg"></i>
                </button>
            </div>

            <form class="space-y-4" @submit.prevent="submitForm">
                <div>
                    <label class="block text-sm font-medium mb-1" for="name">Name</label>
                    <input v-model="form.name" type="text" class="ui-input w-full" required />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                </div>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1" for="display_order">Display Order</label>
                        <input v-model="form.display_order" type="number" min="0" class="ui-input w-full" />
                        <p v-if="form.errors.display_order" class="mt-1 text-sm text-red-600">{{ form.errors.display_order }}</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-1" for="votes_allowed">Total Votes Allowed</label>
                        <input v-model="form.votes_allowed" type="number" min="1" max="50" class="ui-input w-full" />
                        <p v-if="form.errors.votes_allowed" class="mt-1 text-sm text-red-600">{{ form.errors.votes_allowed }}</p>
                    </div>
                </div>

                <div class="p-4 bg-slate-50 border border-slate-200 rounded-lg">
                    <label class="block text-sm font-medium text-slate-800 mb-1" for="max_candidates_per_party">Max Candidates Per Party</label>
                    <p class="text-xs text-slate-500 mb-2">How many candidates can a single partylist field for this specific position?</p>
                    <input v-model="form.max_candidates_per_party" type="number" min="1" :max="form.votes_allowed" class="ui-input w-full sm:w-1/2" />
                    <p v-if="form.errors.max_candidates_per_party" class="mt-1 text-sm text-red-600">{{ form.errors.max_candidates_per_party }}</p>
                </div>

                <div class="flex gap-3 mt-6 pt-4 border-t">
                    <button type="submit" class="ui-btn-primary" :disabled="form.processing">
                        {{ modalMode === 'create' ? 'Save Position' : 'Update Position' }}
                    </button>
                    <button type="button" @click="closeModal" class="ui-btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</template>