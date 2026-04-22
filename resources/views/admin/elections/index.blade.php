@extends('layouts.app')

@section('content')
<div class="ui-page">
    <div class="ui-card min-h-screen flex flex-col">
        @php
            $facilitatorCards = $facilitators->map(fn ($facilitator) => [
                'id' => $facilitator->id,
                'name' => $facilitator->name,
                'username' => $facilitator->username,
                'grade_level' => $facilitator->grade_level,
            ])->values();
        @endphp

        <div class="flex justify-between items-center mb-6">
            <h1 class="text-xl font-semibold">Manage Elections</h1>
            <a href="{{ route('elections.create') }}" class="ui-btn-primary">Create Election</a>
        </div>

        @if (session('status'))
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                <p class="text-green-800">{{ session('status') }}</p>
            </div>
        @endif

        @if (session('error'))
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                <p class="text-red-800">{{ session('error') }}</p>
            </div>
        @endif

        @if ($elections->isEmpty())
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <p class="text-blue-800">No elections found. <a href="{{ route('elections.create') }}" class="font-semibold">Create one</a>.</p>
            </div>
        @else
            <div class="overflow-x-auto max-h-screen border rounded-lg shadow-sm flex-1 flex flex-col">
                <table class="w-full text-sm">
                    <thead class="bg-gray-100 border-b sticky top-0 z-10">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">Election Name</th>
                            <th class="px-4 py-3 text-left font-medium">Election Date</th>
                            <th class="px-4 py-3 text-left font-medium">Facilitator</th>
                            <th class="px-4 py-3 text-left font-medium">Status</th>
                            <th class="px-4 py-3 text-left font-medium">Ballots</th>
                            <th class="px-4 py-3 text-left font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($elections as $election)
                            <tr
                                class="border-b hover:bg-gray-50"
                                x-data="{
                                    assignOpen: false,
                                    search: '',
                                    filterMode: 'all',
                                    facilitators: @js($facilitatorCards),
                                    selectedIds: @js($election->facilitators->pluck('id')->map(fn ($id) => (string) $id)->values()),
                                    filteredFacilitators() {
                                        return this.facilitators.filter((facilitator) => {
                                            const term = this.search.trim().toLowerCase();
                                            const assigned = this.selectedIds.includes(String(facilitator.id));

                                            if (this.filterMode === 'assigned' && !assigned) {
                                                return false;
                                            }

                                            if (this.filterMode === 'unassigned' && assigned) {
                                                return false;
                                            }

                                            if (! term) {
                                                return true;
                                            }

                                            return [facilitator.name, facilitator.username, facilitator.grade_level]
                                                .join(' ')
                                                .toLowerCase()
                                                .includes(term);
                                        });
                                    },
                                    toggleFacilitator(id) {
                                        id = String(id);

                                        if (this.selectedIds.includes(id)) {
                                            this.selectedIds = this.selectedIds.filter((selectedId) => selectedId !== id);
                                            return;
                                        }

                                        this.selectedIds = [...this.selectedIds, id];
                                    },
                                    isSelected(id) {
                                        return this.selectedIds.includes(String(id));
                                    },
                                    selectedCount() {
                                        return this.selectedIds.length;
                                    },
                                    selectedSummary() {
                                        if (! this.selectedCount()) {
                                            return 'No facilitators assigned';
                                        }

                                        if (this.selectedCount() === 1) {
                                            return '1 facilitator assigned';
                                        }

                                        return `${this.selectedCount()} facilitators assigned`;
                                    }
                                }"
                            >
                                <td class="px-4 py-3">{{ $election->election_name }}</td>
                                <td class="px-4 py-3">{{ $election->election_date->format('F j, Y g:i A') }}</td>
                                <td class="px-4 py-3">
                                    <div class="relative inline-flex items-center gap-3">
                                        <div>
                                            <div class="text-sm font-medium text-slate-900">
                                                @if ($election->facilitators->isNotEmpty())
                                                    {{ $election->facilitators->pluck('name')->join(', ') }}
                                                @else
                                                    Unassigned
                                                @endif
                                            </div>
                                        </div>

                                        <button
                                            type="button"
                                            @click="assignOpen = !assignOpen"
                                            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                                        >
                                            Assign Facilitator
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M5.22 7.22a.75.75 0 0 1 1.06 0L10 10.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                                            </svg>
                                        </button>

                                        <div
                                            x-cloak
                                            x-show="assignOpen"
                                            @click.outside="assignOpen = false"
                                            x-transition:enter="transition ease-out duration-100"
                                            x-transition:enter-start="opacity-0 -translate-y-2"
                                            x-transition:enter-end="opacity-100 translate-y-0"
                                            x-transition:leave="transition ease-in duration-75"
                                            x-transition:leave-start="opacity-100 translate-y-0"
                                            x-transition:leave-end="opacity-0 -translate-y-2"
                                            class="absolute top-full left-0 z-30 mt-2 w-80 rounded-lg border border-slate-200 bg-white shadow-lg"
                                        >
                                            <form method="POST" action="{{ route('elections.facilitators.assign', $election) }}" class="flex flex-col">
                                                @csrf
                                                @method('PATCH')

                                                <div class="border-b border-slate-200 p-3">
                                                    <div class="relative mb-2">
                                                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.5 3.75a4.75 4.75 0 1 0 2.98 8.45l3.39 3.39a.75.75 0 1 0 1.06-1.06l-3.39-3.39A4.75 4.75 0 0 0 8.5 3.75Zm-3.25 4.75a3.25 3.25 0 1 1 6.5 0 3.25 3.25 0 0 1-6.5 0Z" clip-rule="evenodd" /></svg>
                                                        <input
                                                            type="text"
                                                            x-model="search"
                                                            placeholder="Search by name, username, or grade"
                                                            class="ui-input w-full pl-9 py-2"
                                                        >
                                                    </div>
                                                    <div class="flex gap-1 text-xs">
                                                        <button type="button" @click="filterMode = 'all'" class="flex-1 rounded px-2 py-1.5 font-medium" :class="filterMode === 'all' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">All</button>
                                                        <button type="button" @click="filterMode = 'assigned'" class="flex-1 rounded px-2 py-1.5 font-medium" :class="filterMode === 'assigned' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">Assigned</button>
                                                        <button type="button" @click="filterMode = 'unassigned'" class="flex-1 rounded px-2 py-1.5 font-medium" :class="filterMode === 'unassigned' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">Unassigned</button>
                                                    </div>
                                                </div>

                                                <div class="max-h-64 overflow-y-auto">
                                                    <template x-if="filteredFacilitators().length === 0">
                                                        <div class="px-4 py-6 text-center text-sm text-slate-500">No facilitators found</div>
                                                    </template>

                                                    <template x-for="facilitator in filteredFacilitators()" :key="facilitator.id">
                                                        <label class="flex cursor-pointer items-center gap-3 border-b border-slate-100 px-4 py-3 transition hover:bg-indigo-50" :class="isSelected(facilitator.id) ? 'bg-indigo-25' : ''">
                                                            <input
                                                                type="checkbox"
                                                                name="facilitator_ids[]"
                                                                :value="facilitator.id"
                                                                :checked="isSelected(facilitator.id)"
                                                                @change="toggleFacilitator(facilitator.id)"
                                                                class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                                            >
                                                            <span class="flex-1">
                                                                <span class="block text-sm font-medium text-slate-900" x-text="facilitator.name"></span>
                                                                <span class="block text-xs text-slate-500" x-text="'@' + facilitator.username + ' | Grade ' + facilitator.grade_level"></span>
                                                            </span>
                                                            <span class="rounded px-2 py-1 text-[11px] font-semibold" :class="isSelected(facilitator.id) ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-500'" x-text="isSelected(facilitator.id) ? '✓' : ''"></span>
                                                        </label>
                                                    </template>
                                                </div>

                                                <div class="border-t border-slate-200 flex items-center justify-between gap-2 bg-slate-50 px-4 py-3">
                                                    <p class="text-xs text-slate-500" x-text="selectedSummary()"></p>
                                                    <div class="flex gap-2">
                                                        <button type="button" @click="selectedIds = [];" class="rounded px-3 py-1.5 text-xs font-semibold text-slate-600 border border-slate-300 hover:bg-slate-100">Clear</button>
                                                        <button type="submit" class="rounded bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800">Save</button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($election->status === 'active')
                                        <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">Active</span>
                                    @elseif ($election->status === 'completed')
                                        <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-800">Completed</span>
                                    @else
                                        <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">Pending</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $election->ballots_count ?? 0 }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex gap-2 flex-wrap">

                                        @if ($election->status === 'pending')
                                            <form method="POST" action="{{ route('elections.start', $election) }}" style="display: inline;">
                                                @csrf
                                                <button type="submit" class="inline-block px-3 py-1 rounded text-xs font-semibold bg-green-500 text-white hover:bg-green-600">Start</button>
                                            </form>
                                        @elseif ($election->status === 'active')
                                            <form method="POST" action="{{ route('elections.stop', $election) }}" style="display: inline;">
                                                @csrf
                                                <button type="submit" class="inline-block px-3 py-1 rounded text-xs font-semibold bg-red-500 text-white hover:bg-red-600">Stop</button>
                                            </form>
                                        @endif

                                        @if ($election->status !== 'active')
                                            <form method="POST" action="{{ route('elections.destroy', $election) }}" style="display: inline;" onsubmit="return confirm('Are you sure?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-block px-3 py-1 rounded text-xs font-semibold bg-red-500 text-white hover:bg-red-600">Delete</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
