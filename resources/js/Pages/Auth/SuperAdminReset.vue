<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    token: {
        type: String,
        required: true,
    },
});

const form = useForm({
    token: props.token,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post('/admin/superadmin');
};
</script>

<template>
    <Head title="Adviser Reset" />

    <main class="mx-auto flex min-h-screen w-full max-w-md items-center px-4 py-8">
        <section class="w-full rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h1 class="text-xl font-semibold tracking-tight text-slate-900">Adviser Password Reset</h1>
            <p class="mt-2 text-sm text-slate-600">Set a new password for the adviser account.</p>

            <form class="mt-6 space-y-4" @submit.prevent="submit">
                <input v-model="form.token" type="hidden" name="token">

                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700">New Password</label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        required
                        autocomplete="new-password"
                        class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                    <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-slate-700">Confirm Password</label>
                    <input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        required
                        autocomplete="new-password"
                        class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                </div>

                <button
                    type="submit"
                    class="inline-flex w-full items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    :disabled="form.processing"
                >
                    Reset Adviser Password
                </button>
            </form>
        </section>
    </main>
</template>
