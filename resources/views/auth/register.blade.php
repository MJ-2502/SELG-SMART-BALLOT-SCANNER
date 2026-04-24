<x-guest-layout>
    <div class="mb-5">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Create Account</h1>
        <p class="mt-2 text-sm text-slate-600">Set up your facilitator account to start managing election workflows.</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <!-- Name -->
        <div>
            <x-input-label for="name" class="text-slate-700" :value="__('Name')" />
            <x-text-input id="name" class="mt-1 block w-full rounded-xl border-slate-200 bg-white/90 px-4 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Username -->
        <div>
            <x-input-label for="username" class="text-slate-700" :value="__('Username')" />
            <x-text-input id="username" class="mt-1 block w-full rounded-xl border-slate-200 bg-white/90 px-4 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500" type="text" name="username" :value="old('username')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('username')" class="mt-2" />
        </div>

        <!-- Password -->
        <div>
            <x-input-label for="password" class="text-slate-700" :value="__('Password')" />

            <div class="relative mt-1">
                <x-text-input id="password" class="block w-full rounded-xl border-slate-200 bg-white/90 px-4 py-2.5 pe-14 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                type="password"
                                name="password"
                                required autocomplete="new-password" />
                <button type="button" data-toggle-password="password" class="absolute inset-y-0 end-0 flex items-center rounded-r-xl px-4 text-xs font-semibold text-slate-500 hover:text-slate-800">
                    {{ __('Show') }}
                </button>
            </div>

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div>
            <x-input-label for="password_confirmation" class="text-slate-700" :value="__('Confirm Password')" />

            <div class="relative mt-1">
                <x-text-input id="password_confirmation" class="block w-full rounded-xl border-slate-200 bg-white/90 px-4 py-2.5 pe-14 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                type="password"
                                name="password_confirmation" required autocomplete="new-password" />
                <button type="button" data-toggle-password="password_confirmation" class="absolute inset-y-0 end-0 flex items-center rounded-r-xl px-4 text-xs font-semibold text-slate-500 hover:text-slate-800">
                    {{ __('Show') }}
                </button>
            </div>

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="mt-6 flex items-center justify-between gap-2">
            <a class="text-sm font-medium text-indigo-600 hover:text-indigo-700 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="rounded-xl border-0 bg-indigo-600 px-5 py-2.5 text-sm font-semibold normal-case tracking-normal shadow-md shadow-indigo-300/50 hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-800">
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>

    <script>
        document.querySelectorAll('[data-toggle-password]').forEach((button) => {
            button.addEventListener('click', () => {
                const targetId = button.getAttribute('data-toggle-password');
                const field = document.getElementById(targetId);
                if (!field) {
                    return;
                }

                const isHidden = field.type === 'password';
                field.type = isHidden ? 'text' : 'password';
                button.textContent = isHidden ? 'Hide' : 'Show';
            });
        });
    </script>
</x-guest-layout>
