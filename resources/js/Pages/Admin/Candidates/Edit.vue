<script setup>
import { computed, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    candidate: Object,
    positions: Array,
    colorPalette: Array,
    usedColors: Array,
    partyColorMap: Object,
});

const form = useForm({
    position_id: props.candidate.position_id ?? '',
    name: props.candidate.name ?? '',
    party: props.candidate.party ?? '',
    color_code: props.candidate.color_code ?? '',
    is_active: Boolean(props.candidate.is_active),
});

const normalizedParty = computed(() => String(form.party ?? '').trim().toLowerCase());
const existingPartyColor = computed(() => props.partyColorMap?.[normalizedParty.value] ?? null);
const unavailableColors = computed(() => {
    const used = new Set((props.usedColors ?? []).map((color) => String(color).toUpperCase()));

    if (existingPartyColor.value) {
        used.delete(String(existingPartyColor.value).toUpperCase());
    }

    return used;
});

watch(existingPartyColor, (value) => {
    if (value) {
        form.color_code = String(value).toUpperCase();
    }
});

const isUnavailable = (color) => unavailableColors.value.has(String(color).toUpperCase());
const selectColor = (color) => {
    if (isUnavailable(color)) {
        return;
    }

    form.color_code = String(color).toUpperCase();
};
</script>

<template>
    <Head title="Edit Candidate" />
    <div class="ui-page-narrow">
        <div class="ui-card">
            <h1 class="text-xl font-semibold mb-4">Edit Candidate</h1>
            <form class="space-y-4" @submit.prevent="form.transform((data) => ({ ...data, _method: 'PATCH' })).post(`/candidates/${candidate.id}`)">
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

                <div>
                    <label class="block text-sm font-medium mb-2">Color Code</label>
                    <p class="text-xs text-slate-600 mb-3">
                        Colors already used by another partylist or independent candidate are disabled.
                    </p>
                    <p v-if="existingPartyColor" class="text-xs text-blue-700 mb-3">
                        This partylist already uses {{ existingPartyColor }}. The same color is automatically applied.
                    </p>
                    <div class="grid grid-cols-6 sm:grid-cols-8 gap-2">
                        <button
                            v-for="color in colorPalette"
                            :key="color"
                            type="button"
                            class="h-9 w-9 rounded-md border-2 transition"
                            :class="[
                                form.color_code === color ? 'border-slate-900 scale-105' : 'border-slate-300',
                                isUnavailable(color) ? 'opacity-40 cursor-not-allowed' : 'hover:scale-105'
                            ]"
                            :style="{ backgroundColor: color }"
                            :disabled="isUnavailable(color)"
                            :title="isUnavailable(color) ? `${color} (already used)` : color"
                            @click="selectColor(color)"
                        />
                    </div>
                    <p v-if="form.color_code" class="text-xs text-slate-700 mt-2">Selected: {{ form.color_code }}</p>
                    <p v-if="form.errors.color_code" class="text-sm text-red-600 mt-1">{{ form.errors.color_code }}</p>
                </div>

                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300" /> Active
                </label>

                <div class="flex gap-3 mt-6">
                    <button type="submit" class="ui-btn-primary" :disabled="form.processing">Update</button>
                    <Link href="/candidates" class="ui-btn-secondary">Cancel</Link>
                </div>
            </form>
        </div>
    </div>
</template>