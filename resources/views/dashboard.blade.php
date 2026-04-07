<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                        @if (auth()->user()?->isAdviser())
                            <p class="mb-4">Adviser access is active.</p>
                            <div class="flex gap-3 flex-wrap">
                                <a href="{{ route('scanner.index') }}" class="underline">Open Scanner</a>
                                <a href="{{ route('admin.dashboard') }}" class="underline">Open Admin Dashboard</a>
                                <a href="{{ route('positions.index') }}" class="underline">Manage Positions</a>
                                <a href="{{ route('candidates.index') }}" class="underline">Manage Candidates</a>
                            </div>
                        @else
                            <p class="mb-4">You're logged in!</p>
                            <div class="flex gap-3 flex-wrap">
                                <a href="{{ route('scanner.index') }}" class="underline">Open Scanner</a>
                            </div>
                        @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
