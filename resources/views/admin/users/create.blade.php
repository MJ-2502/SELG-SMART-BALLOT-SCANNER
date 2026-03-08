@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto p-6">
    <div class="bg-white shadow-sm sm:rounded-lg p-6">
        <h1 class="text-xl font-semibold mb-4">Create User Account</h1>

        <form action="{{ route('users.store') }}" method="POST" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium mb-1" for="name">Name</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" required class="w-full rounded-md border-slate-300" />
                @error('name') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium mb-1" for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required class="w-full rounded-md border-slate-300" />
                @error('email') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium mb-1" for="role">Role</label>
                <select id="role" name="role" required class="w-full rounded-md border-slate-300">
                    <option value="facilitator" @selected(old('role') === 'facilitator')>Facilitator</option>
                    <option value="adviser" @selected(old('role') === 'adviser')>Adviser</option>
                </select>
                @error('role') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium mb-1" for="password">Password</label>
                <input id="password" type="password" name="password" required class="w-full rounded-md border-slate-300" />
                @error('password') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium mb-1" for="password_confirmation">Confirm Password</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required class="w-full rounded-md border-slate-300" />
            </div>

            <div class="flex gap-3">
                <button type="submit" style="padding:8px 14px;border:0;border-radius:6px;background:#1d4ed8;color:#fff;font-weight:600;cursor:pointer;">Create</button>
                <a href="{{ route('users.index') }}" style="display:inline-block;padding:8px 14px;border-radius:6px;background:#334155;color:#fff;text-decoration:none;font-weight:600;">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
