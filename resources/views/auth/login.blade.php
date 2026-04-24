<x-guest-layout>
    <div class="mb-5">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Welcome Back</h1>
        <p class="mt-2 text-sm text-slate-600">
            {{ __('Please enter your credentials to access your account.') }}
        </p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <!-- Username -->
        <div>
            <x-input-label for="username" class="text-slate-700" :value="__('Username')" />
            <x-text-input id="username" class="mt-1 block w-full rounded-xl border-slate-200 bg-white/90 px-4 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500" type="text" name="username" :value="old('username')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('username')" class="mt-2" />
        </div>

        <!-- Password -->
        <div>
            <x-input-label for="password" class="text-slate-700" :value="__('Password')" />

            <div class="relative mt-1">
                <x-text-input id="password" class="block w-full rounded-xl border-slate-200 bg-white/90 px-4 py-2.5 pe-14 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                type="password"
                                name="password"
                                required autocomplete="current-password" />
                <button type="button" data-toggle-password="password" class="absolute inset-y-0 end-0 flex items-center rounded-r-xl px-4 text-xs font-semibold text-slate-500 hover:text-slate-800">
                    {{ __('Show') }}
                </button>
            </div>

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
                <span class="ms-2 text-sm text-slate-600">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="mt-6 flex items-center justify-between gap-2">
            @if (Route::has('register'))
                <a class="text-sm font-medium text-indigo-600 hover:text-indigo-700 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('register') }}">
                    {{ __('Create an facilitator account') }}
                </a>
            @else
                <span></span>
            @endif

            <div class="flex items-center gap-3">
            @if (Route::has('password.request'))
                <a class="text-sm text-slate-500 hover:text-slate-700 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <x-primary-button class="rounded-xl border-0 bg-indigo-600 px-5 py-2.5 text-sm font-semibold normal-case tracking-normal shadow-md shadow-indigo-300/50 hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-800">
                {{ __('Log in') }}
            </x-primary-button>
            </div>
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
