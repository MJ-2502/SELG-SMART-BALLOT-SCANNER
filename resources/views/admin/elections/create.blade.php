@extends('layouts.app')

@section('content')
<div class="ui-page">
    <div class="ui-card">
        <h1 class="text-xl font-semibold mb-6">Create Election</h1>

        @if ($errors->any())
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                <ul class="text-red-800 text-sm list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('elections.store') }}" class="space-y-6">
            @csrf

            <div>
                <label class="block text-sm font-medium mb-2" for="election_name">Election Name</label>
                <input
                    type="text"
                    id="election_name"
                    name="election_name"
                    value="{{ old('election_name') }}"
                    required
                    maxlength="255"
                    class="ui-input"
                    placeholder="e.g., Student Council Election 2026"
                />
                @error('election_name')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium mb-2" for="election_date">Election Date & Time</label>
                <input
                    type="datetime-local"
                    id="election_date"
                    name="election_date"
                    value="{{ old('election_date') }}"
                    required
                    class="ui-input"
                />
                @error('election_date')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-sm text-gray-500">Set a future date and time for the election to begin.</p>
            </div>

            <div>
                <label class="block text-sm font-medium mb-2">Assigned Facilitators</label>
                <div class="border border-slate-200 rounded-lg p-3 max-h-52 overflow-y-auto bg-white">
                    @forelse ($facilitators as $facilitator)
                        <label class="flex items-start gap-3 py-2 border-b border-slate-100 last:border-0">
                            <input
                                type="checkbox"
                                name="facilitator_ids[]"
                                value="{{ $facilitator->id }}"
                                @checked(in_array((string) $facilitator->id, array_map('strval', old('facilitator_ids', [])), true))
                                class="mt-1 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                            >
                            <span>
                                <span class="block text-sm font-medium text-slate-800">{{ $facilitator->name }}</span>
                                <span class="block text-xs text-slate-500">{{ '@' . $facilitator->username }} | Grade {{ $facilitator->grade_level }}</span>
                            </span>
                        </label>
                    @empty
                        <p class="text-sm text-slate-500">No facilitator accounts found. Create facilitator credentials first.</p>
                    @endforelse
                </div>
                @error('facilitator_ids')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
                @error('facilitator_ids.*')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-sm text-gray-500">Select one or more facilitators who are allowed to scan ballots for this election.</p>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="ui-btn-primary">Create Election</button>
                <a href="{{ route('elections.index') }}" class="ui-btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
