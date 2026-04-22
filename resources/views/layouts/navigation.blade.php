@php
    $isAdviser = auth()->user()?->isAdviser();
    $dashboardHref = $isAdviser ? route('admin.dashboard') : route('dashboard');
    $navItems = [
        [
            'label' => 'Dashboard',
            'href' => $dashboardHref,
            'active' => request()->routeIs('dashboard') || request()->routeIs('admin.dashboard'),
            'icon' => 'M3 12a9 9 0 1 1 18 0 9 9 0 0 1-18 0Zm6.75 3.75h4.5V9h-1.5v5.25h-3v1.5Z',
        ],
        [
            'label' => 'Scanner',
            'href' => route('scanner.index'),
            'active' => request()->routeIs('scanner.*'),
            'icon' => 'M3 6.75A3.75 3.75 0 0 1 6.75 3h10.5A3.75 3.75 0 0 1 21 6.75v10.5A3.75 3.75 0 0 1 17.25 21H6.75A3.75 3.75 0 0 1 3 17.25V6.75Zm3.75-.75a.75.75 0 0 0-.75.75v10.5c0 .414.336.75.75.75h10.5a.75.75 0 0 0 .75-.75V6.75a.75.75 0 0 0-.75-.75H6.75Zm.75 3h9v1.5h-9V9Zm0 4.5h9V15h-9v-1.5Z',
        ],
    ];

    if ($isAdviser) {
        $navItems[] = ['label' => 'Election Management', 'href' => route('elections.index'), 'active' => request()->routeIs('elections.*'), 'icon' => 'M6 3.75h12A2.25 2.25 0 0 1 20.25 6v12A2.25 2.25 0 0 1 18 20.25H6A2.25 2.25 0 0 1 3.75 18V6A2.25 2.25 0 0 1 6 3.75Zm.75 5.25h10.5v1.5H6.75V9Zm0 3.75h10.5v1.5H6.75v-1.5Zm0 3.75h6v1.5h-6v-1.5Z'];
        $navItems[] = ['label' => 'Facilitator Management', 'href' => route('facilitators.index'), 'active' => request()->routeIs('facilitators.*'), 'icon' => 'M15 8.25a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm-9 9a6 6 0 1 1 12 0v.75H6v-.75Z'];
        $navItems[] = ['label' => 'Positions', 'href' => route('positions.index'), 'active' => request()->routeIs('positions.*'), 'icon' => 'M5.25 4.5h13.5v3H5.25v-3Zm0 6h13.5v3H5.25v-3Zm0 6h13.5v3H5.25v-3Z'];
        $navItems[] = ['label' => 'Candidates', 'href' => route('candidates.index'), 'active' => request()->routeIs('candidates.*'), 'icon' => 'M12 3.75a4.5 4.5 0 1 1 0 9 4.5 4.5 0 0 1 0-9Zm-6.75 15a6.75 6.75 0 0 1 13.5 0V20.25H5.25v-1.5Z'];
        $navItems[] = ['label' => 'Ballot Generator', 'href' => route('admin.ballot-generator.index'), 'active' => request()->routeIs('admin.ballot-generator.*'), 'icon' => 'M6 3.75h8.25L18 7.5v12.75H6V3.75Zm7.5.44V8.25h4.06l-4.06-4.06ZM8.25 11.25h7.5v1.5h-7.5v-1.5Zm0 3.75h7.5v1.5h-7.5V15Z'];
        $navItems[] = ['label' => 'Ballot Management', 'href' => route('admin.ballot-management.index'), 'active' => request()->routeIs('admin.ballot-management.*'), 'icon' => 'M3.75 6A2.25 2.25 0 0 1 6 3.75h12A2.25 2.25 0 0 1 20.25 6v12A2.25 2.25 0 0 1 18 20.25H6A2.25 2.25 0 0 1 3.75 18V6Zm4.5 2.25h7.5v1.5h-7.5v-1.5Zm0 3.75h7.5v1.5h-7.5V12Zm0 3.75H12v1.5H8.25v-1.5Z'];
    }
@endphp

<nav class="relative">
    <div x-cloak x-show="sidebarOpen" class="fixed inset-0 z-40 md:hidden">
        <div class="absolute inset-0 bg-slate-950/35" @click="sidebarOpen = false"></div>
        <aside class="absolute left-0 top-0 flex h-full w-72 flex-col bg-slate-50 text-slate-800 shadow-2xl">
            <div class="flex h-16 items-center justify-between border-b border-slate-200 px-5">
                <div class="flex items-center gap-3">
                    <div class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-600 text-white">
                        <x-application-logo class="h-5 w-5 fill-current" />
                    </div>
                    <div>
                        <div class="text-sm font-semibold leading-4 text-slate-900">SELG Ballot Scanner</div>
                        <div class="text-xs text-slate-500">Navigation</div>
                    </div>
                </div>
                <button @click="sidebarOpen = false" class="rounded-md p-2 text-slate-500 hover:bg-slate-200 hover:text-slate-800">
                    <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto px-4 py-5">
                <div class="rounded-2xl border border-slate-200 bg-white p-2 shadow-sm">
                @foreach ($navItems as $item)
                    <a href="{{ $item['href'] }}" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition {{ $item['active'] ? 'bg-indigo-100 text-indigo-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                        <svg class="h-5 w-5 shrink-0 {{ $item['active'] ? 'text-indigo-600' : 'text-slate-400' }}" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="{{ $item['icon'] }}" />
                        </svg>
                        <span>{{ __($item['label']) }}</span>
                    </a>
                @endforeach
                </div>
            </div>
        </aside>
    </div>

    <aside class="fixed bottom-6 left-4 top-[5.75rem] z-10 hidden w-64 flex-col rounded-2xl border border-slate-200 bg-white/92 text-slate-800 shadow-lg shadow-slate-200/80 backdrop-blur md:flex">
        <div class="flex-1 overflow-y-auto px-3 py-3">
            @foreach ($navItems as $item)
                <a href="{{ $item['href'] }}" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition {{ $item['active'] ? 'bg-indigo-100 text-indigo-700 shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                    <svg class="h-5 w-5 shrink-0 {{ $item['active'] ? 'text-indigo-600' : 'text-slate-400' }}" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="{{ $item['icon'] }}" />
                    </svg>
                    <span>{{ __($item['label']) }}</span>
                </a>
            @endforeach
        </div>
    </aside>
</nav>
