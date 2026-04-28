<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    mustVerifyEmail: Boolean,
    status: String,
});

const page = usePage();
const user = page.props.auth?.user ?? {};

const profileForm = useForm({
    name: user.name ?? '',
    username: user.username ?? '',
    email: user.email ?? '',
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const deleteForm = useForm({
    password: '',
});

function updateProfile() {
    profileForm.patch('/profile', {
        preserveScroll: true,
    });
}

function updatePassword() {
    passwordForm.put('/password', {
        preserveScroll: true,
        errorBag: 'updatePassword',
        onSuccess: () => passwordForm.reset(),
    });
}

</script>

<template>
    <Head title="Profile" />

    <div class="ui-page">
        <div class="space-y-6 max-w-3xl mx-auto">
            <div class="ui-card">
                <h1 class="text-xl font-semibold mb-2">Profile Information</h1>
                <p class="text-sm text-slate-600 mb-6">Update your account name, username, and email address.</p>

                <form class="space-y-4" @submit.prevent="updateProfile">
                    <div>
                        <label class="block text-sm font-medium mb-1">Name</label>
                        <input v-model="profileForm.name" type="text" class="ui-input" required />
                        <p v-if="profileForm.errors.name" class="mt-1 text-sm text-red-600">{{ profileForm.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Username</label>
                        <input v-model="profileForm.username" type="text" class="ui-input" required />
                        <p v-if="profileForm.errors.username" class="mt-1 text-sm text-red-600">{{ profileForm.errors.username }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Email</label>
                        <input v-model="profileForm.email" type="email" class="ui-input" required />
                        <p v-if="profileForm.errors.email" class="mt-1 text-sm text-red-600">{{ profileForm.errors.email }}</p>
                    </div>

                    <p v-if="mustVerifyEmail && user.email_verified_at === null" class="text-sm text-amber-700">
                        Your email address is unverified.
                    </p>

                    <p v-if="status === 'profile-updated'" class="text-sm text-emerald-700">Profile updated successfully.</p>

                    <button type="submit" class="ui-btn-primary" :disabled="profileForm.processing">Save Profile</button>
                </form>
            </div>

            <div class="ui-card">
                <h2 class="text-xl font-semibold mb-2">Update Password</h2>
                <p class="text-sm text-slate-600 mb-6">Use a long and secure password for your account.</p>

                <form class="space-y-4" @submit.prevent="updatePassword">
                    <div>
                        <label class="block text-sm font-medium mb-1">Current Password</label>
                        <input v-model="passwordForm.current_password" type="password" class="ui-input" required />
                        <p v-if="passwordForm.errors.current_password" class="mt-1 text-sm text-red-600">{{ passwordForm.errors.current_password }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">New Password</label>
                        <input v-model="passwordForm.password" type="password" class="ui-input" required />
                        <p v-if="passwordForm.errors.password" class="mt-1 text-sm text-red-600">{{ passwordForm.errors.password }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Confirm New Password</label>
                        <input v-model="passwordForm.password_confirmation" type="password" class="ui-input" required />
                    </div>

                    <p v-if="status === 'password-updated'" class="text-sm text-emerald-700">Password updated successfully.</p>

                    <button type="submit" class="ui-btn-primary" :disabled="passwordForm.processing">Update Password</button>
                </form>
            </div>
        </div>
    </div>
</template>