@extends('layouts.app')

@section('content')
<div class="card">
    <h1>Edit Candidate</h1>

    <form action="{{ route('candidates.update', $candidate) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="mb-12">
            <label for="position_id">Position</label><br>
            <select id="position_id" name="position_id" required>
                <option value="">Select position</option>
                @foreach ($positions as $position)
                    <option value="{{ $position->id }}" @selected(old('position_id', $candidate->position_id) == $position->id)>{{ $position->name }}</option>
                @endforeach
            </select>
            @error('position_id') <div class="error">{{ $message }}</div> @enderror
        </div>

        <div class="mb-12">
            <label for="name">Name</label><br>
            <input id="name" type="text" name="name" value="{{ old('name', $candidate->name) }}" required>
            @error('name') <div class="error">{{ $message }}</div> @enderror
        </div>

        <div class="mb-12">
            <label for="party">Party</label><br>
            <input id="party" type="text" name="party" value="{{ old('party', $candidate->party) }}">
            @error('party') <div class="error">{{ $message }}</div> @enderror
        </div>

        <div class="mb-12">
            <label>
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $candidate->is_active))>
                Active
            </label>
        </div>

        <div class="actions">
            <button type="submit" class="btn btn-primary">Update</button>
            <a class="btn btn-muted" href="{{ route('candidates.index') }}">Cancel</a>
        </div>
    </form>
</div>
@endsection
