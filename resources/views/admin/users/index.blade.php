@extends('layouts.app')

@section('content')
<div class="ui-page">
    <div class="ui-card">
        <div class="flex justify-between items-center mb-4">
            <h1 class="text-xl font-semibold">User Accounts</h1>
            <a href="{{ route('users.create') }}" class="ui-btn-primary">Add User</a>
        </div>

        <div class="overflow-x-auto">
            <table class="ui-table">
                <thead>
                    <tr class="ui-row">
                        <th class="ui-th">ID</th>
                        <th class="ui-th">Name</th>
                        <th class="ui-th">Email</th>
                        <th class="ui-th">Role</th>
                        <th class="ui-th">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr class="ui-row">
                            <td class="ui-td">{{ $user->id }}</td>
                            <td class="ui-td">{{ $user->name }}</td>
                            <td class="ui-td">{{ $user->email }}</td>
                            <td class="ui-td">{{ ucfirst($user->role) }}</td>
                            <td class="ui-td">
                                <div class="flex gap-2">
                                    <a href="{{ route('users.edit', $user) }}" class="ui-btn-secondary ui-btn-sm">Edit</a>
                                    <form action="{{ route('users.destroy', $user) }}" method="POST" onsubmit="return confirm('Delete this user account?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ui-btn-danger ui-btn-sm">Delete</button>
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
