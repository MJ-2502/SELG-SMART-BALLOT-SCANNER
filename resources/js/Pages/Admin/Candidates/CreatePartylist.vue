<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    positions: Array,
    colorPalette: Array,
    usedColors: Array,
    partyColorMap: Object,
});

const form = useForm({ party: '', color_code: '', is_active: true, entries: {} });

// We now generate an ARRAY of empty strings based on the max allowed candidates per party
props.positions.forEach((position) => {
    const maxAllowed = position.max_candidates_per_party || 1;
    form.entries[position.id] = Array(maxAllowed).fill('');
});

const existingParties = computed(() => {
    if (!props.partyColorMap) return [];
    return Object.keys(props.partyColorMap).map(key => key.toUpperCase());
});

const isNewParty = ref(existingParties.value.length === 0);

const togglePartyMode = () => {
    isNewParty.value = !isNewParty.value;
    form.party = ''; 
};

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
    if (isUnavailable(color)) return;
    form.color_code = String(color).toUpperCase();
};
</script>

<template>
    <Head title="Add Partylist Candidates" />
    <div class="ui-page-narrow">
        <div class="ui-card">
            <h1 class="text-xl font-semibold mb-2">Add Partylist Candidates</h1>
            <p class="text-gray-600 mb-6">Enter one party name, then input multiple candidates by position in one go.</p>
            
            <form class="space-y-6" @submit.prevent="form.post('/candidates/partylist')">
                
                <div>
                    <div class="flex items-end justify-between mb-1">
                        <label class="block text-sm font-medium" for="party">Partylist Name</label>
                    </div>
                    <input v-model="form.party" type="text" class="ui-input" placeholder="Enter new partylist name" required />
                    <p v-if="form.errors.party" class="text-sm text-red-600 mt-1">{{ form.errors.party }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Partylist Color Code</label>
                    <div class="grid grid-cols-6 sm:grid-cols-8 gap-2">
                        <button v-for="color in colorPalette" :key="color" type="button" class="h-9 w-9 rounded-md border-2 transition"
                            :class="[form.color_code === color ? 'border-slate-900 scale-105' : 'border-slate-300', isUnavailable(color) ? 'opacity-40 cursor-not-allowed' : 'hover:scale-105']"
                            :style="{ backgroundColor: color }" :disabled="isUnavailable(color)" @click="selectColor(color)" />
                    </div>
                    <p v-if="form.errors.color_code" class="text-sm text-red-600 mt-1">{{ form.errors.color_code }}</p>
                </div>

                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300" /> Mark all as active
                </label>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-5">
                    <h2 class="text-base font-semibold mb-4 border-b pb-2">Candidates Roster</h2>

                    <div class="space-y-6">
                        <div v-for="position in positions" :key="position.id" class="space-y-2">
                            <label class="block text-sm font-bold text-slate-800">
                                {{ position.name }} <span class="text-xs font-normal text-slate-500 ml-1">(Max allowed: {{ position.max_candidates_per_party || 1 }})</span>
                            </label>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <input
                                    v-for="(slot, index) in (position.max_candidates_per_party || 1)"
                                    :key="index"
                                    v-model="form.entries[position.id][index]"
                                    type="text"
                                    :placeholder="`Candidate ${index + 1}`"
                                    class="ui-input"
                                />
                            </div>
                        </div>
                    </div>

                    <p v-if="form.errors.entries" class="text-sm text-red-600 mt-2">{{ form.errors.entries }}</p>
                </div>

                <div class="flex gap-3 mt-6">
                    <button type="submit" class="ui-btn-primary" :disabled="form.processing">Save Partylist Roster</button>
                    <Link href="/candidates" class="ui-btn-secondary">Cancel</Link>
                </div>
            </form>
        </div>
    </div>
</template>