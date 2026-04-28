<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';

defineOptions({ layout: GuestLayout });

const form = useForm({ email: '' });

function submit() {
    form.post('/forgot-password', {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Forgot Password" />

    <div class="mb-4 text-sm text-gray-600">
        Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.
    </div>

    <div v-if="$page.props.flash?.status" class="mb-4 font-medium text-sm text-green-600">
        {{ $page.props.flash.status }}
    </div>

    <form @submit.prevent="submit">
        <div>
            <label for="email" class="block font-medium text-sm text-gray-700">Email</label>
            <input id="email" v-model="form.email" class="block mt-1 w-full rounded-xl border-slate-200 bg-white/90 px-4 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500" type="email" required autofocus />
            <p v-if="form.errors.email" class="mt-2 text-sm text-red-600">{{ form.errors.email }}</p>
        </div>

        <div class="flex items-center justify-end mt-4 gap-3">
            <Link href="/login" class="text-sm text-slate-600 hover:text-slate-900">Back to login</Link>
            <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-xl font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150" :disabled="form.processing">
                Email Password Reset Link
            </button>
        </div>
    </form>
</template>