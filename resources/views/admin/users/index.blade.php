@extends('layouts.app')

@section('content')
<div class="ui-page">
    <div class="ui-card">
        <div class="flex justify-between items-center mb-4">
            <h1 class="text-xl font-semibold">Facilitator Credentials</h1>
            <a href="{{ route('facilitators.create') }}" class="ui-btn-primary">Add Facilitator</a>
        </div>

        <div class="overflow-x-auto">
            <table class="ui-table">
                <thead>
                    <tr class="ui-row">
                        <th class="ui-th">ID</th>
                        <th class="ui-th">Name</th>
                        <th class="ui-th">Username</th>
                        <th class="ui-th">Grade Level</th>
                        <th class="ui-th">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr class="ui-row">
                            <td class="ui-td">{{ $user->id }}</td>
                            <td class="ui-td">{{ $user->name }}</td>
                            <td class="ui-td">{{ $user->username }}</td>
                            <td class="ui-td">{{ $user->grade_level }}</td>
                            <td class="ui-td">
                                <div class="flex gap-2">
                                    <a href="{{ route('facilitators.edit', $user) }}" class="ui-btn-secondary ui-btn-sm">Edit</a>
                                    <form action="{{ route('facilitators.destroy', $user) }}" method="POST" onsubmit="return confirm('Delete this facilitator account?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ui-btn-danger ui-btn-sm">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="p-2" colspan="5">No facilitator accounts found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
