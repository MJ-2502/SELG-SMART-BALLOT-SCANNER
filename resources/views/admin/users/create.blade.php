@extends('layouts.app')

@section('content')
<div class="ui-page-narrow">
    <div class="ui-card">
        <h1 class="text-xl font-semibold mb-4">Create Facilitator Credentials</h1>

        <form action="{{ route('facilitators.store') }}" method="POST" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium mb-1" for="name">Full Name</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" required class="ui-input" />
                @error('name') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium mb-1" for="username">Username</label>
                <input id="username" type="text" name="username" value="{{ old('username') }}" required class="ui-input" />
                @error('username') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium mb-1" for="grade_level">Grade Level</label>
                <input id="grade_level" type="text" name="grade_level" value="{{ old('grade_level') }}" required class="ui-input" />
                @error('grade_level') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium mb-1" for="password">Password</label>
                <input id="password" type="password" name="password" required class="ui-input" />
                @error('password') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium mb-1" for="password_confirmation">Confirm Password</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required class="ui-input" />
            </div>

            <div class="flex gap-3">
                <button type="submit" class="ui-btn-primary">Create</button>
                <a href="{{ route('facilitators.index') }}" class="ui-btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
