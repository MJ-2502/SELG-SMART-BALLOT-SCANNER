@extends('layouts.app')

@section('content')
<div class="card">
    <div class="actions mb-12">
        <h1 style="margin:0;">Positions</h1>
        <a class="btn btn-primary" href="{{ route('positions.create') }}">Add Position</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Display Order</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($positions as $position)
                <tr>
                    <td>{{ $position->id }}</td>
                    <td>{{ $position->name }}</td>
                    <td>{{ $position->display_order }}</td>
                    <td class="actions">
                        <a class="btn btn-muted" href="{{ route('positions.edit', $position) }}">Edit</a>
                        <form action="{{ route('positions.destroy', $position) }}" method="POST" onsubmit="return confirm('Delete this position?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">No positions yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
