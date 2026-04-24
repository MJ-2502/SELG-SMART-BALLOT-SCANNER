@php
    $isAdviser = auth()->user()?->isAdviser();
    $dashboardHref = $isAdviser ? route('admin.dashboard') : route('dashboard');
    $navItems = [
        [
            'label' => 'Dashboard',
            'href' => $dashboardHref,
            'active' => request()->routeIs('dashboard') || request()->routeIs('admin.dashboard'),
            'icon' => 'bi bi-graph-up',
        ],
        [
            'label' => 'Ballot Scanner',
            'href' => route('scanner.index'),
            'active' => request()->routeIs('scanner.*'),
            'icon' => 'bi bi-upc-scan',
        ],
    ];

    if ($isAdviser) {
        $navItems[] = ['label' => 'Election Management', 'href' => route('elections.index'), 'active' => request()->routeIs('elections.*'), 'icon' => 'bi bi-calendar-check'];
        $navItems[] = ['label' => 'Facilitators', 'href' => route('facilitators.index'), 'active' => request()->routeIs('facilitators.*'), 'icon' => 'bi bi-people'];
        $navItems[] = ['label' => 'Positions', 'href' => route('positions.index'), 'active' => request()->routeIs('positions.*'), 'icon' => 'bi bi-list-task'];
        $navItems[] = ['label' => 'Candidates', 'href' => route('candidates.index'), 'active' => request()->routeIs('candidates.*'), 'icon' => 'bi bi-person-badge'];
        $navItems[] = ['label' => 'Reports', 'href' => route('admin.reports.index'), 'active' => request()->routeIs('admin.reports.*'), 'icon' => 'bi bi-file-earmark-bar-graph'];
        $navItems[] = ['label' => 'Ballot Management', 'href' => route('admin.ballot-management.index'), 'active' => request()->routeIs('admin.ballot-management.*'), 'icon' => 'bi bi-ui-checks-grid'];
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
                    <i class="bi bi-x-lg text-base leading-none" aria-hidden="true"></i>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto px-4 py-5">
                <div class="rounded-2xl border border-slate-200 bg-white p-2 shadow-sm">
                @foreach ($navItems as $item)
                    <a href="{{ $item['href'] }}" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition {{ $item['active'] ? 'bg-indigo-100 text-indigo-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                        <i class="{{ $item['icon'] }} text-base leading-none shrink-0 {{ $item['active'] ? 'text-indigo-600' : 'text-slate-400' }}" aria-hidden="true"></i>
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
                    <i class="{{ $item['icon'] }} text-base leading-none shrink-0 {{ $item['active'] ? 'text-indigo-600' : 'text-slate-400' }}" aria-hidden="true"></i>
                    <span>{{ __($item['label']) }}</span>
                </a>
            @endforeach
        </div>
    </aside>
</nav>
