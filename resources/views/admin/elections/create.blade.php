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

            <div class="flex gap-2">
                <button type="submit" class="ui-btn-primary">Create Election</button>
                <a href="{{ route('elections.index') }}" class="ui-btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
