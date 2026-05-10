<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const form = useForm({
    name: '',
    gender: '',
    grade_level: '',
    section: '',
    username: '',
    password: '',
    password_confirmation: ''
});

const submit = () => {
    form.post('/facilitators');
};
</script>

<template>
    <Head title="Add Facilitator" />
    <div class="ui-page-narrow">
        <div class="ui-card p-8">
            <div class="mb-6">
                <h1 class="text-xl font-bold text-slate-900">Add Facilitator</h1>
                <p class="text-sm text-slate-500 mt-1">Register a new facilitator account for the system.</p>
            </div>

            <form @submit.prevent="submit" class="space-y-8">
                
                <div>
                    <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">Personal Details</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        
                        <div class="md:col-span-3">
                            <label class="block text-sm font-medium mb-1 text-slate-700">Full Name</label>
                            <input v-model="form.name" type="text" class="ui-input" required />
                            <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1 text-slate-700">Gender</label>
                            <select v-model="form.gender" class="ui-input" required>
                                <option value="" disabled>Select</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                            <p v-if="form.errors.gender" class="mt-1 text-sm text-red-600">{{ form.errors.gender }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1 text-slate-700">Grade Level</label>
                            <input v-model="form.grade_level" type="text" class="ui-input" />
                            <p v-if="form.errors.grade_level" class="mt-1 text-sm text-red-600">{{ form.errors.grade_level }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1 text-slate-700">Section</label>
                            <input v-model="form.section" type="text" class="ui-input" />
                            <p v-if="form.errors.section" class="mt-1 text-sm text-red-600">{{ form.errors.section }}</p>
                        </div>

                    </div>
                </div>

                <hr class="border-slate-200" />

                <div>
                    <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">Account Credentials</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-1 text-slate-700">Username</label>
                            <input v-model="form.username" type="text" class="ui-input" required />
                            <p v-if="form.errors.username" class="mt-1 text-sm text-red-600">{{ form.errors.username }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1 text-slate-700">Password</label>
                            <input v-model="form.password" type="password" class="ui-input" required />
                            <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1 text-slate-700">Confirm Password</label>
                            <input v-model="form.password_confirmation" type="password" class="ui-input" required />
                        </div>

                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4">
                    <Link href="/facilitators" class="ui-btn-secondary">Cancel</Link>
                    <button type="submit" class="ui-btn-primary" :disabled="form.processing">Save Facilitator</button>
                </div>
            </form>
        </div>
    </div>
</template>