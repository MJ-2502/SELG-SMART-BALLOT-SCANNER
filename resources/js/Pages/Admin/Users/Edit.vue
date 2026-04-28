<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({ user: Object });

const form = useForm({
    name: props.user.name ?? '',
    username: props.user.username ?? '',
    grade_level: props.user.grade_level ?? '',
    password: '',
    password_confirmation: '',
});
</script>

<template>
    <Head title="Edit Facilitator" />
    <div class="ui-page-narrow">
        <div class="ui-card">
            <h1 class="text-xl font-semibold mb-4">Edit Facilitator</h1>

            <form class="space-y-4" @submit.prevent="form.transform((data) => ({ ...data, _method: 'PATCH' })).post(`/facilitators/${user.id}`)">
                <div>
                    <label class="block text-sm font-medium mb-1" for="name">Full Name</label>
                    <input v-model="form.name" type="text" class="ui-input" />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Username</label>
                    <input v-model="form.username" type="text" class="ui-input" />
                    <p v-if="form.errors.username" class="mt-1 text-sm text-red-600">{{ form.errors.username }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Grade Level</label>
                    <input v-model="form.grade_level" type="text" class="ui-input" />
                    <p v-if="form.errors.grade_level" class="mt-1 text-sm text-red-600">{{ form.errors.grade_level }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Password</label>
                    <input v-model="form.password" type="password" class="ui-input" />
                    <p class="text-xs text-gray-500 mt-1">Leave blank to keep current password.</p>
                    <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Confirm Password</label>
                    <input v-model="form.password_confirmation" type="password" class="ui-input" />
                </div>

                <div class="flex gap-3 mt-6">
                    <button type="submit" class="ui-btn-primary" :disabled="form.processing">Update</button>
                    <Link href="/facilitators" class="ui-btn-secondary">Cancel</Link>
                </div>
            </form>
        </div>
    </div>
</template>