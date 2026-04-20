@extends('layouts.app')

@section('content')
@php
    $candidateItems = method_exists($candidates, 'getCollection') ? $candidates->getCollection() : $candidates;
    $partyGroups = $candidateItems
        ->groupBy(fn ($candidate) => filled($candidate->party) ? $candidate->party : 'Independent')
        ->map(function ($group) {
            return $group->sortBy(function ($candidate) {
                $positionOrder = $candidate->position?->display_order ?? 9999;
                $positionName = strtolower((string) ($candidate->position?->name ?? ''));
                $candidateName = strtolower((string) $candidate->name);

                return sprintf('%05d|%s|%s', $positionOrder, $positionName, $candidateName);
            })->values();
        })
        ->sortKeys();
@endphp

<div class="ui-page">
    <div class="ui-card mb-6">
        <div class="flex justify-between items-center">
            <h1 class="text-xl font-semibold">Candidates</h1>
            <div class="flex gap-2">
                @if ($hasElection)
                    <a href="{{ route('candidates.create') }}" class="ui-btn-primary">Add Candidate</a>
                    <a href="{{ route('candidates.partylist.create') }}" class="ui-btn-secondary">Add Partylist</a>
                @else
                    <span class="ui-btn-primary opacity-50 cursor-not-allowed" aria-disabled="true">Add Candidate</span>
                    <span class="ui-btn-secondary opacity-50 cursor-not-allowed" aria-disabled="true">Add Partylist</span>
                @endif
            </div>
        </div>
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

    @unless ($hasElection)
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <p class="text-blue-800">
                Create an election first before registering candidates or creating a partylist.
                <a href="{{ route('elections.create') }}" class="font-semibold underline">Create election</a>.
            </p>
        </div>
    @endunless

    @forelse ($partyGroups as $party => $partyCandidates)
        @php
            $firstCandidate = $partyCandidates->first();
            $partyValue = trim((string) ($firstCandidate?->party ?? ''));
        @endphp
        <div class="ui-card mb-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-lg font-semibold">{{ $party }}</h2>
                    <span class="text-sm text-gray-500">{{ $partyCandidates->count() }} candidate(s)</span>
                </div>

                @if ($partyValue !== '')
                    <form action="{{ route('candidates.partylist.destroy') }}" method="POST" onsubmit="return confirm('Delete this partylist and all its candidates?');">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="party" value="{{ $partyValue }}">
                        <button type="submit" class="ui-btn-danger ui-btn-sm">Delete Partylist</button>
                    </form>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="ui-table">
                    <thead>
                        <tr class="ui-row">
                            <th class="ui-th">ID</th>
                            <th class="ui-th">Name</th>
                            <th class="ui-th">Position</th>
                            <th class="ui-th">Active</th>
                            <th class="ui-th">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($partyCandidates as $candidate)
                            <tr class="ui-row">
                                <td class="ui-td">{{ $candidate->id }}</td>
                                <td class="ui-td">{{ $candidate->name }}</td>
                                <td class="ui-td">{{ $candidate->position?->name }}</td>
                                <td class="ui-td">{{ $candidate->is_active ? 'Yes' : 'No' }}</td>
                                <td class="ui-td">
                                    <div class="flex gap-2">
                                        <a href="{{ route('candidates.edit', $candidate) }}" class="ui-btn-secondary ui-btn-sm">Edit</a>
                                        <form action="{{ route('candidates.destroy', $candidate) }}" method="POST" onsubmit="return confirm('Delete this candidate?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="ui-btn-danger ui-btn-sm">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="ui-card">
            <p class="text-gray-600">No candidates yet.</p>
        </div>
    @endforelse
</div>
@endsection
