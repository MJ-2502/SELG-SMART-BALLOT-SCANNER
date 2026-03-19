@extends('layouts.app')

@section('content')
<div class="card">
    <h1>Add Partylist Candidates</h1>
    <p>Enter one party name, then input candidate names by position in one form.</p>

    <form action="{{ route('candidates.partylist.store') }}" method="POST">
        @csrf

        <div class="mb-12">
            <label for="party">Partylist Name</label><br>
            <input id="party" type="text" name="party" value="{{ old('party') }}" required>
            @error('party') <div class="error">{{ $message }}</div> @enderror
        </div>

        <div class="mb-12">
            <label>
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))>
                Mark all as active
            </label>
        </div>

        <div class="mb-12" style="border:1px solid #e5e7eb; border-radius:8px; padding:10px;">
            <h2 style="margin-top:0; margin-bottom:10px; font-size:16px;">Candidates Per Position</h2>

            @foreach ($positions as $position)
                <div class="mb-12">
                    <label for="entry_{{ $position->id }}">{{ $position->name }}</label><br>
                    <input
                        id="entry_{{ $position->id }}"
                        type="text"
                        name="entries[{{ $position->id }}]"
                        value="{{ old('entries.'.$position->id) }}"
                        placeholder="Candidate name for {{ $position->name }}"
                    >
                </div>
            @endforeach

            @error('entries') <div class="error">{{ $message }}</div> @enderror
        </div>

        <div class="actions">
            <button type="submit" class="btn btn-primary">Save Partylist</button>
            <a class="btn btn-muted" href="{{ route('candidates.index') }}">Cancel</a>
        </div>
    </form>
</div>
@endsection
