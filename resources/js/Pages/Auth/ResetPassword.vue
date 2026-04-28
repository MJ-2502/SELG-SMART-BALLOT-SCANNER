<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';

defineOptions({ layout: GuestLayout });

const props = defineProps({ token: String, email: String });

const form = useForm({
    token: props.token,
    email: props.email ?? '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/reset-password', {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Reset Password" />

    <form @submit.prevent="submit">
        <input type="hidden" v-model="form.token" />

        <div>
            <label for="email" class="block font-medium text-sm text-gray-700">Email</label>
            <input id="email" v-model="form.email" class="block mt-1 w-full rounded-xl border-slate-200 bg-white/90 px-4 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500" type="email" required autocomplete="username" />
            <p v-if="form.errors.email" class="mt-2 text-sm text-red-600">{{ form.errors.email }}</p>
        </div>

        <div class="mt-4">
            <label for="password" class="block font-medium text-sm text-gray-700">Password</label>
            <input id="password" v-model="form.password" class="block mt-1 w-full rounded-xl border-slate-200 bg-white/90 px-4 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500" type="password" required autocomplete="new-password" />
            <p v-if="form.errors.password" class="mt-2 text-sm text-red-600">{{ form.errors.password }}</p>
        </div>

        <div class="mt-4">
            <label for="password_confirmation" class="block font-medium text-sm text-gray-700">Confirm Password</label>
            <input id="password_confirmation" v-model="form.password_confirmation" class="block mt-1 w-full rounded-xl border-slate-200 bg-white/90 px-4 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500" type="password" required autocomplete="new-password" />
            <p v-if="form.errors.password_confirmation" class="mt-2 text-sm text-red-600">{{ form.errors.password_confirmation }}</p>
        </div>

        <div class="flex items-center justify-end mt-4">
            <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-xl font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150" :disabled="form.processing">
                Reset Password
            </button>
        </div>
    </form>
</template>