<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const form = useForm({ name: '', username: '', grade_level: '', password: '', password_confirmation: '' });
</script>

<template>
    <Head title="Add Facilitator" />
    <div class="ui-page-narrow">
        <div class="ui-card">
            <h1 class="text-xl font-semibold mb-4">Add Facilitator</h1>

            <form class="space-y-4" @submit.prevent="form.post('/facilitators')">
                <div>
                    <label class="block text-sm font-medium mb-1" for="name">Full Name</label>
                    <input v-model="form.name" type="text" class="ui-input" />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" for="username">Username</label>
                    <input v-model="form.username" type="text" class="ui-input" />
                    <p v-if="form.errors.username" class="mt-1 text-sm text-red-600">{{ form.errors.username }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" for="grade_level">Grade Level</label>
                    <input v-model="form.grade_level" type="text" class="ui-input" />
                    <p v-if="form.errors.grade_level" class="mt-1 text-sm text-red-600">{{ form.errors.grade_level }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" for="password">Password</label>
                    <input v-model="form.password" type="password" class="ui-input" />
                    <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" for="password_confirmation">Confirm Password</label>
                    <input v-model="form.password_confirmation" type="password" class="ui-input" />
                </div>

                <div class="flex gap-3 mt-6">
                    <button type="submit" class="ui-btn-primary" :disabled="form.processing">Save</button>
                    <Link href="/facilitators" class="ui-btn-secondary">Cancel</Link>
                </div>
            </form>
        </div>
    </div>
</template>