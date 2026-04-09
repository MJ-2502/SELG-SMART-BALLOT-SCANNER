@extends('layouts.app')

@section('content')
<div class="ui-page-narrow">
    <div class="ui-card">
        <h1 class="text-xl font-semibold mb-4">Create User Account</h1>

        <form action="{{ route('users.store') }}" method="POST" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium mb-1" for="name">Name</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" required class="ui-input" />
                @error('name') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium mb-1" for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required class="ui-input" />
                @error('email') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium mb-1" for="role">Role</label>
                <select id="role" name="role" required class="ui-input">
                    <option value="facilitator" @selected(old('role') === 'facilitator')>Facilitator</option>
                    <option value="adviser" @selected(old('role') === 'adviser')>Adviser</option>
                </select>
                @error('role') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
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
                <a href="{{ route('users.index') }}" class="ui-btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
