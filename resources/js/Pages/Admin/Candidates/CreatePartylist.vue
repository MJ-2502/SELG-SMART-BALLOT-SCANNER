<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({ positions: Array });
const form = useForm({ party: '', is_active: true, entries: {} });

props.positions.forEach((position) => {
    form.entries[position.id] = '';
});
</script>

<template>
    <Head title="Add Partylist Candidates" />
    <div class="ui-page-narrow">
        <div class="ui-card">
            <h1 class="text-xl font-semibold mb-2">Add Partylist Candidates</h1>
            <p class="text-gray-600 mb-6">Enter one party name, then input candidate names by position in one form.</p>
            <form class="space-y-4" @submit.prevent="form.post('/candidates/partylist')">
                <div>
                    <label class="block text-sm font-medium mb-1" for="party">Partylist Name</label>
                    <input v-model="form.party" type="text" class="ui-input" required />
                    <p v-if="form.errors.party" class="text-sm text-red-600 mt-1">{{ form.errors.party }}</p>
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300" /> Mark all as active
                </label>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <h2 class="text-base font-semibold mb-3">Candidates Per Position</h2>

                    <div class="space-y-4">
                        <div v-for="position in positions" :key="position.id">
                            <label class="block text-sm font-medium mb-1" :for="`entry_${position.id}`">{{ position.name }}</label>
                            <input
                                :id="`entry_${position.id}`"
                                v-model="form.entries[position.id]"
                                type="text"
                                :placeholder="`Candidate name for ${position.name}`"
                                class="ui-input"
                            />
                        </div>
                    </div>

                    <p v-if="form.errors.entries" class="text-sm text-red-600 mt-2">{{ form.errors.entries }}</p>
                </div>

                <div class="flex gap-3 mt-6">
                    <button type="submit" class="ui-btn-primary" :disabled="form.processing">Save Partylist</button>
                    <Link href="/candidates" class="ui-btn-secondary">Cancel</Link>
                </div>
            </form>
        </div>
    </div>
</template>