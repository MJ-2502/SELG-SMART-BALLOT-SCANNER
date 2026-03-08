@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto p-6">
    <div class="bg-white shadow-sm sm:rounded-lg p-6">
        <div class="flex justify-between items-center mb-4">
            <h1 class="text-xl font-semibold">User Accounts</h1>
            <a href="{{ route('users.create') }}" style="display:inline-block;padding:8px 14px;border-radius:6px;background:#1d4ed8;color:#fff;text-decoration:none;font-weight:600;">Add User</a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full border-collapse">
                <thead>
                    <tr class="border-b">
                        <th class="text-left p-2">ID</th>
                        <th class="text-left p-2">Name</th>
                        <th class="text-left p-2">Email</th>
                        <th class="text-left p-2">Role</th>
                        <th class="text-left p-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr class="border-b">
                            <td class="p-2">{{ $user->id }}</td>
                            <td class="p-2">{{ $user->name }}</td>
                            <td class="p-2">{{ $user->email }}</td>
                            <td class="p-2">{{ ucfirst($user->role) }}</td>
                            <td class="p-2">
                                <div class="flex gap-2">
                                    <a href="{{ route('users.edit', $user) }}" style="display:inline-block;padding:6px 12px;border-radius:6px;background:#334155;color:#fff;text-decoration:none;font-weight:600;">Edit</a>
                                    <form action="{{ route('users.destroy', $user) }}" method="POST" onsubmit="return confirm('Delete this user account?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" style="padding:6px 12px;border:0;border-radius:6px;background:#dc2626;color:#fff;font-weight:600;cursor:pointer;">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="p-2" colspan="5">No user accounts found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
