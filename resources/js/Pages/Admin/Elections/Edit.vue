<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({ election: Object, facilitators: Array });

// Keep the exact wall-clock datetime without timezone conversion.
const toDateTimeLocalInput = (value) => {
    if (!value) return '';
    return String(value).replace(' ', 'T').slice(0, 16);
};

const initialDate = toDateTimeLocalInput(props.election.election_date ?? '');

const form = useForm({
    election_name: props.election.election_name || '',
    election_date: initialDate,
    facilitator_ids: props.election.facilitators?.map((f) => f.id) ?? [],
});

const submitForm = () => {
    form.patch(`/elections/${props.election.id}`);
};
</script>

<template>
    <Head title="Edit Election" />
    <div class="ui-page-narrow">
        <div class="ui-card">
            <h1 class="text-xl font-semibold mb-6">Edit Election</h1>
            <form class="space-y-4" @submit.prevent="submitForm">
                <div>
                    <label class="block text-sm font-medium mb-1">Election Name</label>
                    <input v-model="form.election_name" type="text" class="ui-input" />
                    <p v-if="form.errors.election_name" class="text-sm text-red-600 mt-1">{{ form.errors.election_name }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Election Date</label>
                    <input v-model="form.election_date" type="datetime-local" class="ui-input" />
                    <p v-if="form.errors.election_date" class="text-sm text-red-600 mt-1">{{ form.errors.election_date }}</p>
                    <p class="mt-1 text-sm text-gray-500">Set a future date and time for the election to begin.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Assign Facilitators</label>
                    <div class="space-y-2 rounded-xl border border-slate-200 p-4 max-h-64 overflow-y-auto">
                        <label v-for="facilitator in facilitators" :key="facilitator.id" class="flex items-center gap-2 text-sm text-slate-700">
                            <input v-model="form.facilitator_ids" type="checkbox" :value="facilitator.id" class="rounded border-slate-300" />
                            <span>{{ facilitator.name }} ({{ facilitator.username }})</span>
                        </label>
                    </div>
                    <p v-if="form.errors.facilitator_ids" class="text-sm text-red-600 mt-1">{{ form.errors.facilitator_ids }}</p>
                    <p class="mt-1 text-sm text-gray-500">Select one or more facilitators who are allowed to scan ballots for this election.</p>
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="ui-btn-primary" :disabled="form.processing">Save changes</button>
                    <a href="/elections" class="ui-btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</template>
