@extends('layouts.app')

@section('content')
<div class="ui-page">
    <div class="ui-card">
        <div class="flex justify-between items-center mb-4">
            <h1 class="text-xl font-semibold">Positions</h1>
            <a href="{{ route('positions.create') }}" class="ui-btn-primary">Add Position</a>
        </div>

        <div class="overflow-x-auto">
            <table class="ui-table">
                <thead>
                    <tr class="ui-row">
                        <th class="ui-th">ID</th>
                        <th class="ui-th">Name</th>
                        <th class="ui-th">Display Order</th>
                        <th class="ui-th">Votes Allowed</th>
                        <th class="ui-th">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($positions as $position)
                        <tr class="ui-row">
                            <td class="ui-td">{{ $position->id }}</td>
                            <td class="ui-td">{{ $position->name }}</td>
                            <td class="ui-td">{{ $position->display_order }}</td>
                            <td class="ui-td">{{ $position->votes_allowed ?? 1 }}</td>
                            <td class="ui-td">
                                <div class="flex gap-2">
                                    <a href="{{ route('positions.edit', $position) }}" class="ui-btn-secondary ui-btn-sm">Edit</a>
                                    <form action="{{ route('positions.destroy', $position) }}" method="POST" onsubmit="return confirm('Delete this position?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ui-btn-danger ui-btn-sm">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="p-2" colspan="5">No positions yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
