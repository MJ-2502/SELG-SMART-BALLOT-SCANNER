<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({ position: Object });

const form = useForm({
    name: props.position.name ?? '',
    display_order: props.position.display_order ?? 0,
    votes_allowed: props.position.votes_allowed ?? 1,
});
</script>

<template>
    <Head title="Edit Position" />
    <div class="ui-page-narrow">
        <div class="ui-card">
            <h1 class="text-xl font-semibold mb-6">Edit Position</h1>
            <form class="space-y-4" @submit.prevent="form.transform((data) => ({ ...data, _method: 'PATCH' })).post(`/positions/${position.id}`)">
                <div>
                    <label class="block text-sm font-medium mb-1">Name</label>
                    <input v-model="form.name" type="text" class="ui-input" />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Display Order</label>
                    <input v-model="form.display_order" type="number" min="0" class="ui-input" />
                    <p v-if="form.errors.display_order" class="mt-1 text-sm text-red-600">{{ form.errors.display_order }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Allowed votes for this position</label>
                    <input v-model="form.votes_allowed" type="number" min="1" max="20" class="ui-input" />
                    <p class="text-sm text-gray-500 mt-1">Set how many candidates a voter may select for this position.</p>
                    <p v-if="form.errors.votes_allowed" class="mt-1 text-sm text-red-600">{{ form.errors.votes_allowed }}</p>
                </div>
                <div class="flex gap-3 mt-6">
                    <button type="submit" class="ui-btn-primary" :disabled="form.processing">Update</button>
                    <Link href="/positions" class="ui-btn-secondary">Cancel</Link>
                </div>
            </form>
        </div>
    </div>
</template>