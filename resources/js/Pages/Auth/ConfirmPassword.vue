<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';

defineOptions({ layout: GuestLayout });

const form = useForm({ password: '' });

function submit() {
    form.post('/confirm-password', {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Confirm Password" />

    <div class="mb-4 text-sm text-gray-600">
        This is a secure area of the application. Please confirm your password before continuing.
    </div>

    <form @submit.prevent="submit">
        <div>
            <label for="password" class="block font-medium text-sm text-gray-700">Password</label>
            <input id="password" v-model="form.password" class="block mt-1 w-full rounded-xl border-slate-200 bg-white/90 px-4 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500" type="password" required autocomplete="current-password" />
            <p v-if="form.errors.password" class="mt-2 text-sm text-red-600">{{ form.errors.password }}</p>
        </div>

        <div class="flex justify-end mt-4">
            <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-xl font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150" :disabled="form.processing">
                Confirm
            </button>
        </div>
    </form>
</template>