<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({ positions: Array });
const form = useForm({ position_id: '', name: '', party: '', is_active: true });
</script>

<template>
    <Head title="Add Candidate" />
    <div class="ui-page-narrow">
        <div class="ui-card">
            <h1 class="text-xl font-semibold mb-4">Add Candidate</h1>
            <form class="space-y-4" @submit.prevent="form.post('/candidates')">
                <div>
                    <label class="block text-sm font-medium mb-1" for="position_id">Position</label>
                    <select v-model="form.position_id" class="ui-input" required>
                        <option value="">Select position</option>
                        <option v-for="position in positions" :key="position.id" :value="position.id">{{ position.name }}</option>
                    </select>
                    <p v-if="form.errors.position_id" class="text-sm text-red-600 mt-1">{{ form.errors.position_id }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" for="name">Name</label>
                    <input v-model="form.name" type="text" class="ui-input" required />
                    <p v-if="form.errors.name" class="text-sm text-red-600 mt-1">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" for="party">Party</label>
                    <input v-model="form.party" type="text" class="ui-input" />
                    <p v-if="form.errors.party" class="text-sm text-red-600 mt-1">{{ form.errors.party }}</p>
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300" /> Active
                </label>

                <div class="flex gap-3 mt-6">
                    <button type="submit" class="ui-btn-primary" :disabled="form.processing">Save</button>
                    <Link href="/candidates" class="ui-btn-secondary">Cancel</Link>
                </div>
            </form>
        </div>
    </div>
</template>