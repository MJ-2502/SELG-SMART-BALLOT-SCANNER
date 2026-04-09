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
                <a href="{{ route('candidates.create') }}" class="ui-btn-primary">Add Candidate</a>
                <a href="{{ route('candidates.partylist.create') }}" class="ui-btn-secondary">Add Partylist</a>
            </div>
        </div>
    </div>

    @forelse ($partyGroups as $party => $partyCandidates)
        <div class="ui-card mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold">{{ $party }}</h2>
                <span class="text-sm text-gray-500">{{ $partyCandidates->count() }} candidate(s)</span>
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
