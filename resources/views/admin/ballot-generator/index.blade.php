@extends('layouts.app')

@section('content')
<div class="ui-page">
    <div class="ui-card">
        <h1 class="text-xl font-semibold mb-2">Ballot Generator</h1>
        <p class="text-gray-600 mb-6">Set how many ballots should be printable for one election. The system assigns a unique ballot number per election to prevent duplication.</p>

        @if (session('status'))
            <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-emerald-900">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-red-800">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (! $activeElection)
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-amber-900">
                No active election found. Start an election from Manage Elections first.
            </div>
        @else
            <form action="{{ route('admin.ballot-generator.generate') }}" method="POST" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-sm font-medium mb-1">Active Election</label>
                    <div class="ui-input bg-slate-50 text-slate-700">
                        {{ $activeElection->label }} (Generated: {{ $activeElection->ballots_count }})
                    </div>
                    <p class="mt-1 text-sm text-gray-500">Ballot generation always targets the currently active election.</p>
                    @error('active_election') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1" for="print_count">Printable ballot count</label>
                    <input id="print_count" type="number" name="print_count" min="1" max="5000" value="{{ old('print_count', 50) }}" required class="ui-input" />
                    <p class="mt-1 text-sm text-gray-500">Example: entering 200 means this election should have 200 uniquely numbered printable ballots.</p>
                    @error('print_count') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="flex gap-3 mt-6">
                    <button type="submit" class="ui-btn-primary">Generate Ballots and Open Print Layout</button>
                </div>
            </form>

        @endif
    </div>
</div>

<div class="ui-page">
    <div class="ui-card">
        <h2 class="text-xl font-semibold mb-2">Current Ballot Content Preview</h2>
        <p class="text-gray-500 mb-4">This preview is based on active candidates grouped by position.</p>

        @forelse ($positions as $position)
            <div class="mb-4 rounded-xl border border-slate-200 p-4">
                <div class="font-semibold">{{ $position->name }}</div>
                <ul class="mt-2 list-disc pl-5">
                    @forelse ($position->candidates as $candidate)
                        <li>{{ $candidate->name }} @if($candidate->party)({{ $candidate->party }})@endif</li>
                    @empty
                        <li class="text-gray-500">No active candidates for this position.</li>
                    @endforelse
                </ul>
            </div>
        @empty
            <p>No positions found yet.</p>
        @endforelse
    </div>
</div>

@endsection
