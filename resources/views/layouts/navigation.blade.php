@php
    $isAdviser = auth()->user()?->isAdviser();
    $dashboardHref = $isAdviser ? route('admin.dashboard') : route('dashboard');
    $navItems = [
        ['label' => 'Dashboard', 'href' => $dashboardHref, 'active' => request()->routeIs('dashboard') || request()->routeIs('admin.dashboard')],
        ['label' => 'Scanner', 'href' => route('scanner.index'), 'active' => request()->routeIs('scanner.*')],
    ];

    if ($isAdviser) {
        $navItems[] = ['label' => 'Users', 'href' => route('users.index'), 'active' => request()->routeIs('users.*')];
        $navItems[] = ['label' => 'Positions', 'href' => route('positions.index'), 'active' => request()->routeIs('positions.*')];
        $navItems[] = ['label' => 'Candidates', 'href' => route('candidates.index'), 'active' => request()->routeIs('candidates.*')];
        $navItems[] = ['label' => 'Ballot Layout', 'href' => route('admin.ballot-layout.index'), 'active' => request()->routeIs('admin.ballot-layout.*')];
    }
@endphp

<nav class="relative">
    <div class="md:hidden border-b border-slate-800 bg-slate-950 text-slate-100">
        <div class="flex h-16 items-center justify-between px-4">
            <a href="{{ $dashboardHref }}" class="flex items-center gap-3">
                <x-application-logo class="block h-9 w-auto fill-current text-white" />
                <div>
                    <div class="text-sm font-semibold leading-4">{{ config('app.name', 'Laravel') }}</div>
                    <div class="text-xs text-slate-400">Navigation</div>
                </div>
            </a>

            <button @click="sidebarOpen = true" class="inline-flex items-center justify-center rounded-md p-2 text-slate-300 hover:bg-slate-800 hover:text-white focus:outline-none focus:ring-2 focus:ring-white/40">
                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        </div>
    </div>

    <div x-cloak x-show="sidebarOpen" class="fixed inset-0 z-40 md:hidden">
        <div class="absolute inset-0 bg-slate-950/60" @click="sidebarOpen = false"></div>
        <aside class="absolute left-0 top-0 flex h-full w-72 flex-col bg-slate-950 text-slate-100 shadow-2xl">
            <div class="flex h-16 items-center justify-between border-b border-slate-800 px-5">
                <div class="flex items-center gap-3">
                    <x-application-logo class="block h-9 w-auto fill-current text-white" />
                    <div>
                        <div class="text-sm font-semibold leading-4">{{ config('app.name', 'Laravel') }}</div>
                        <div class="text-xs text-slate-400">Menu</div>
                    </div>
                </div>
                <button @click="sidebarOpen = false" class="rounded-md p-2 text-slate-300 hover:bg-slate-800 hover:text-white">
                    <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
                @foreach ($navItems as $item)
                    <a href="{{ $item['href'] }}" class="block rounded-lg px-4 py-3 text-sm font-medium transition {{ $item['active'] ? 'bg-indigo-500/15 text-white ring-1 ring-inset ring-indigo-400/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        {{ __($item['label']) }}
                    </a>
                @endforeach
            </div>

            <div class="border-t border-slate-800 p-4">
                <div class="mb-4">
                    <div class="text-sm font-semibold text-white">{{ Auth::user()->name }}</div>
                    <div class="text-xs text-slate-400">{{ Auth::user()->email }}</div>
                </div>
                <div class="space-y-2">
                    <a href="{{ route('profile.edit') }}" class="block rounded-lg px-4 py-2 text-sm text-slate-300 hover:bg-slate-800 hover:text-white">Profile</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="block w-full rounded-lg px-4 py-2 text-left text-sm text-slate-300 hover:bg-slate-800 hover:text-white">Log Out</button>
                    </form>
                </div>
            </div>
        </aside>
    </div>

    <aside class="fixed inset-y-0 left-0 z-30 hidden w-72 flex-col border-r border-slate-900 bg-slate-950 text-slate-100 shadow-2xl md:flex">
        <div class="flex h-16 items-center gap-3 border-b border-slate-800 px-6">
            <x-application-logo class="block h-9 w-auto fill-current text-white" />
            <div>
                <div class="text-sm font-semibold leading-4">{{ config('app.name', 'Laravel') }}</div>
                <div class="text-xs text-slate-400">Adviser console</div>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
            @foreach ($navItems as $item)
                <a href="{{ $item['href'] }}" class="block rounded-lg px-4 py-3 text-sm font-medium transition {{ $item['active'] ? 'bg-indigo-500/15 text-white ring-1 ring-inset ring-indigo-400/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                    {{ __($item['label']) }}
                </a>
            @endforeach
        </div>

        <div class="border-t border-slate-800 p-4">
            <div class="mb-4 rounded-xl border border-slate-800 bg-slate-900/80 p-4">
                <div class="text-sm font-semibold text-white">{{ Auth::user()->name }}</div>
                <div class="text-xs text-slate-400">{{ Auth::user()->email }}</div>
            </div>
            <div class="space-y-2">
                <a href="{{ route('profile.edit') }}" class="block rounded-lg px-4 py-2 text-sm text-slate-300 hover:bg-slate-800 hover:text-white">Profile</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="block w-full rounded-lg px-4 py-2 text-left text-sm text-slate-300 hover:bg-slate-800 hover:text-white">Log Out</button>
                </form>
            </div>
        </div>
    </aside>
</nav>
