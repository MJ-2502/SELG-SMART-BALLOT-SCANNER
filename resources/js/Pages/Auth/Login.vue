<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';

defineOptions({ layout: GuestLayout });

const form = useForm({
    username: '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/login', {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Log in" />

    <div class="mb-5">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Welcome Back</h1>
        <p class="mt-2 text-sm text-slate-600">Please enter your credentials to access your account.</p>
    </div>

    <div v-if="$page.props.flash?.status" class="mb-4 font-medium text-sm text-green-600">
        {{ $page.props.flash.status }}
    </div>

    <form class="space-y-4" @submit.prevent="submit">
        <div>
            <label for="username" class="block font-medium text-sm text-gray-700">Username</label>
            <input
                id="username"
                v-model="form.username"
                class="mt-1 block w-full rounded-xl border-slate-200 bg-white/90 px-4 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                type="text"
                required
                autofocus
                autocomplete="username"
            />
            <p v-if="form.errors.username" class="mt-2 text-sm text-red-600">{{ form.errors.username }}</p>
        </div>

        <div>
            <label for="password" class="block font-medium text-sm text-gray-700">Password</label>
            <div class="relative mt-1">
                <input
                    id="password"
                    v-model="form.password"
                    class="block w-full rounded-xl border-slate-200 bg-white/90 px-4 py-2.5 pe-14 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                    :type="form.showPassword ? 'text' : 'password'"
                    required
                    autocomplete="current-password"
                />
                <button
                    type="button"
                    class="absolute inset-y-0 end-0 flex items-center rounded-r-xl px-4 text-xs font-semibold text-slate-500 hover:text-slate-800"
                    @click="form.showPassword = !form.showPassword"
                >
                    {{ form.showPassword ? 'Hide' : 'Show' }}
                </button>
            </div>
            <p v-if="form.errors.password" class="mt-2 text-sm text-red-600">{{ form.errors.password }}</p>
        </div>

        <div class="flex items-center justify-between gap-3">
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input v-model="form.remember" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                <span>Remember me</span>
            </label>
            <Link href="/forgot-password" class="text-sm text-indigo-600 hover:text-indigo-700">Forgot password?</Link>
        </div>

        <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-xl font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 w-full justify-center" :disabled="form.processing">
            Log in
        </button>
    </form>
</template>