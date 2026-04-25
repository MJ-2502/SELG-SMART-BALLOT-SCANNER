<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({ targetElection: Object, positions: Array });

const form = useForm({ election_id: props.targetElection?.id ?? '', print_count: 50 });
</script>

<template>
    <Head title="Ballot Generator" />
    <div class="ui-page">
        <div class="ui-card">
            <div class="flex items-center justify-between gap-3 mb-6">
                <div>
                    <h1 class="text-xl font-semibold">Ballot Generator</h1>
                    <p class="text-gray-600">Set how many ballots should be printable for one election. The system assigns a unique ballot number per election to prevent duplication.</p>
                </div>
            </div>

            <div v-if="$page.props.flash.status" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-emerald-900">
                {{ $page.props.flash.status }}
            </div>

            <div v-if="Object.keys(form.errors).length" class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-red-800">
                <ul class="list-disc pl-5">
                    <li v-for="(error, key) in form.errors" :key="key">{{ error }}</li>
                </ul>
            </div>

            <div v-if="!targetElection" class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-amber-900">
                No target election found. Choose an election from Manage Elections or start an active election first.
            </div>

            <form v-else class="space-y-4" @submit.prevent="form.post('/admin/ballot-generator/generate')">
                <input type="hidden" v-model="form.election_id" />

                <div>
                    <label class="block text-sm font-medium mb-1">Target Election</label>
                    <div class="ui-input bg-slate-50 text-slate-700">
                        {{ targetElection.label }} (Generated: {{ targetElection.ballots_count }})
                    </div>
                    <p class="mt-1 text-sm text-gray-500">This election was auto-selected from Manage Elections. You can also open this page without an election to target the current active election.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1" for="print_count">Printable ballot count</label>
                    <input id="print_count" v-model="form.print_count" type="number" min="1" max="5000" required class="ui-input" />
                    <p class="mt-1 text-sm text-gray-500">Example: entering 200 means this election should have 200 uniquely numbered printable ballots.</p>
                </div>

                <div class="flex gap-3 mt-6">
                    <button type="submit" class="ui-btn-primary">Generate Ballots and Open Print Layout</button>
                </div>
            </form>
        </div>
    </div>

    <div class="ui-page">
        <div class="ui-card">
            <h2 class="text-xl font-semibold mb-2">Current Ballot Content Preview</h2>
            <p class="text-gray-500 mb-4">This preview is based on active candidates grouped by position.</p>

            <div v-if="positions.length === 0">No positions found yet.</div>

            <div v-for="position in positions" :key="position.id" class="mb-4 rounded-xl border border-slate-200 p-4">
                <div class="font-semibold">{{ position.name }}</div>
                <ul class="mt-2 list-disc pl-5">
                    <li v-for="candidate in position.candidates" :key="candidate.id">
                        {{ candidate.name }} <span v-if="candidate.party">({{ candidate.party }})</span>
                    </li>
                    <li v-if="position.candidates.length === 0" class="text-gray-500">No active candidates for this position.</li>
                </ul>
            </div>
        </div>
    </div>
</template>