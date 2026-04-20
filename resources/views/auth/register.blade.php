<x-guest-layout>
    <a href="{{ route('auth.google.redirect') }}" class="mb-4 inline-flex w-full items-center justify-center gap-3 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
        <svg class="h-5 w-5" viewBox="0 0 24 24" aria-hidden="true">
            <path fill="#EA4335" d="M12 10.2v3.9h5.5c-.2 1.2-.9 2.3-1.9 3.1l3 2.3c1.8-1.7 2.8-4.1 2.8-6.9 0-.7-.1-1.5-.2-2.2H12z"/>
            <path fill="#34A853" d="M12 22c2.7 0 5-0.9 6.6-2.5l-3-2.3c-.9.6-2 1-3.6 1-2.8 0-5.1-1.9-5.9-4.4l-3.1 2.4C4.6 19.6 8 22 12 22z"/>
            <path fill="#FBBC05" d="M6.1 13.8C5.9 13.2 5.8 12.6 5.8 12s.1-1.2.3-1.8L3 7.8C2.3 9.1 2 10.5 2 12s.3 2.9 1 4.2l3.1-2.4z"/>
            <path fill="#4285F4" d="M12 5.8c1.5 0 2.9.5 4 1.6l3-3C17 2.5 14.8 1.5 12 1.5 8 1.5 4.6 3.9 3 7.8l3.1 2.4c.8-2.6 3.1-4.4 5.9-4.4z"/>
        </svg>
        <span>{{ __('Continue with Google') }}</span>
    </a>

    <div class="mb-4 text-center text-xs uppercase tracking-wide text-gray-400">{{ __('or create with username') }}</div>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Username -->
        <div class="mt-4">
            <x-input-label for="username" :value="__('Username')" />
            <x-text-input id="username" class="block mt-1 w-full" type="text" name="username" :value="old('username')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('username')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <div class="relative mt-1">
                <x-text-input id="password" class="block w-full pe-12"
                                type="password"
                                name="password"
                                required autocomplete="new-password" />
                <button type="button" data-toggle-password="password" class="absolute inset-y-0 end-0 flex items-center px-3 text-xs font-medium text-gray-600 hover:text-gray-900">
                    {{ __('Show') }}
                </button>
            </div>

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <div class="relative mt-1">
                <x-text-input id="password_confirmation" class="block w-full pe-12"
                                type="password"
                                name="password_confirmation" required autocomplete="new-password" />
                <button type="button" data-toggle-password="password_confirmation" class="absolute inset-y-0 end-0 flex items-center px-3 text-xs font-medium text-gray-600 hover:text-gray-900">
                    {{ __('Show') }}
                </button>
            </div>

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="ms-4">
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
