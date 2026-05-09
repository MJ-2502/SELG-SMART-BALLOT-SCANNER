<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';

defineOptions({ layout: GuestLayout });

const form = useForm({
    username: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

function submit() {
    form.post('/login', {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Log in" />

    <div class="mb-8 text-center">
        <h1 class="text-3xl font-bold tracking-tight text-slate-900">Welcome Back</h1>
        <p class="mt-2 text-sm text-slate-600">Please enter your credentials to access your account.</p>
    </div>

    <div v-if="$page.props.flash?.status" class="mb-6 rounded-xl border border-green-200 bg-green-50 p-4 font-medium text-sm text-green-600">
        {{ $page.props.flash.status }}
    </div>

    <form class="space-y-6" @submit.prevent="submit">
        <div>
            <label for="username" class="block font-medium text-sm text-gray-700 mb-1.5">Username</label>
            <input
                id="username"
                v-model="form.username"
                class="block w-full rounded-xl border-slate-200 bg-white/90 px-4 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 transition-colors"
                type="text"
                required
                autofocus
                autocomplete="username"
            />
            </div>

        <div>
            <label for="password" class="block font-medium text-sm text-gray-700 mb-1.5">Password</label>
            <div class="relative">
                <input
                    id="password"
                    v-model="form.password"
                    class="block w-full rounded-xl border-slate-200 bg-white/90 px-4 py-2.5 pe-16 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 transition-colors"
                    :type="showPassword ? 'text' : 'password'"
                    required
                    autocomplete="current-password"
                />
                <button
                    type="button"
                    class="absolute inset-y-0 end-0 flex items-center rounded-r-xl px-4 text-xs font-semibold text-slate-500 hover:text-slate-800 transition-colors"
                    @click="showPassword = !showPassword"
                >
                    {{ showPassword ? 'Hide' : 'Show' }}
                </button>
            </div>
            
            <div v-if="form.errors.username || form.errors.password" class="mt-2 text-sm text-red-600">
                <p v-if="form.errors.username">{{ form.errors.username }}</p>
                <p v-if="form.errors.password && !form.errors.username">{{ form.errors.password }}</p>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <label class="flex items-center cursor-pointer">
                <input 
                    type="checkbox" 
                    v-model="form.remember" 
                    class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500" 
                />
                <span class="ml-2 text-sm text-slate-600">Remember me</span>
            </label>
        </div>

        <button 
            type="submit" 
            class="inline-flex w-full items-center justify-center rounded-xl border border-transparent bg-gray-800 px-4 py-3 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 active:bg-gray-900 disabled:opacity-50" 
            :disabled="form.processing"
        >
            Log in
        </button>
    </form>
</template>