@extends('layouts.app')

@section('content')
<div class="ui-page-narrow">
    <div class="ui-card">
        <h1 class="text-xl font-semibold mb-4">Edit Position</h1>

        <form action="{{ route('positions.update', $position) }}" method="POST" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-sm font-medium mb-1" for="name">Name</label>
                <input id="name" type="text" name="name" value="{{ old('name', $position->name) }}" required class="ui-input" />
                @error('name') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium mb-1" for="display_order">Display Order</label>
                <input id="display_order" type="number" min="0" name="display_order" value="{{ old('display_order', $position->display_order) }}" class="ui-input" />
                @error('display_order') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium mb-1" for="votes_allowed">Allowed votes for this position</label>
                <input id="votes_allowed" type="number" min="1" max="20" name="votes_allowed" value="{{ old('votes_allowed', $position->votes_allowed ?? 1) }}" required class="ui-input" />
                <p class="text-sm text-gray-500 mt-1">Set how many candidates a voter may select for this position.</p>
                @error('votes_allowed') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-3 mt-6">
                <button type="submit" class="ui-btn-primary">Update</button>
                <a href="{{ route('positions.index') }}" class="ui-btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
