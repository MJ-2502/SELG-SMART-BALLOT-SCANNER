<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';

defineOptions({ layout: GuestLayout });

const form = useForm({});

function resend() {
    form.post('/email/verification-notification', {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Email Verification" />

    <div class="mb-4 text-sm text-gray-600">
        Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn't receive the email, we will gladly send you another.
    </div>

    <div v-if="$page.props.flash?.status === 'verification-link-sent'" class="mb-4 font-medium text-sm text-green-600">
        A new verification link has been sent to the email address you provided during registration.
    </div>

    <div class="mt-4 flex items-center justify-between">
        <button type="button" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-xl font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150" :disabled="form.processing" @click="resend">
            Resend Verification Email
        </button>

        <Link href="/logout" method="post" as="button" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
            Log Out
        </Link>
    </div>
</template>