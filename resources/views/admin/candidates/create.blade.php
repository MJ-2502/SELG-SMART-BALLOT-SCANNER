@extends('layouts.app')

@section('content')
<div class="ui-page-narrow">
    <div class="ui-card">
        <h1 class="text-xl font-semibold mb-4">Add Candidate</h1>

        <form action="{{ route('candidates.store') }}" method="POST" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium mb-1" for="position_id">Position</label>
                <select id="position_id" name="position_id" required class="ui-input">
                    <option value="">Select position</option>
                    @foreach ($positions as $position)
                        <option value="{{ $position->id }}" @selected(old('position_id') == $position->id)>{{ $position->name }}</option>
                    @endforeach
                </select>
                @error('position_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium mb-1" for="name">Name</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" required class="ui-input" />
                @error('name') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium mb-1" for="party">Party</label>
                <input id="party" type="text" name="party" value="{{ old('party') }}" class="ui-input" />
                @error('party') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center">
                <label class="flex items-center">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) class="rounded border-slate-300" />
                    <span class="ml-2 text-sm font-medium">Active</span>
                </label>
            </div>

            <div class="flex gap-3 mt-6">
                <button type="submit" class="ui-btn-primary">Save</button>
                <a href="{{ route('candidates.index') }}" class="ui-btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
