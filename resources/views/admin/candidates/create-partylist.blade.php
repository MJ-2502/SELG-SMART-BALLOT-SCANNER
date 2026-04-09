@extends('layouts.app')

@section('content')
<div class="ui-page-narrow">
    <div class="ui-card">
        <h1 class="text-xl font-semibold mb-2">Add Partylist Candidates</h1>
        <p class="text-gray-600 mb-6">Enter one party name, then input candidate names by position in one form.</p>

        <form action="{{ route('candidates.partylist.store') }}" method="POST" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium mb-1" for="party">Partylist Name</label>
                <input id="party" type="text" name="party" value="{{ old('party') }}" required class="ui-input" />
                @error('party') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center">
                <label class="flex items-center">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) class="rounded border-slate-300" />
                    <span class="ml-2 text-sm font-medium">Mark all as active</span>
                </label>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <h2 class="text-base font-semibold mb-3">Candidates Per Position</h2>

                <div class="space-y-4">
                    @foreach ($positions as $position)
                        <div>
                            <label class="block text-sm font-medium mb-1" for="entry_{{ $position->id }}">{{ $position->name }}</label>
                            <input
                                id="entry_{{ $position->id }}"
                                type="text"
                                name="entries[{{ $position->id }}]"
                                value="{{ old('entries.'.$position->id) }}"
                                placeholder="Candidate name for {{ $position->name }}"
                                class="ui-input"
                            />
                        </div>
                    @endforeach
                </div>

                @error('entries') <p class="text-sm text-red-600 mt-2">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-3 mt-6">
                <button type="submit" class="ui-btn-primary">Save Partylist</button>
                <a href="{{ route('candidates.index') }}" class="ui-btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
