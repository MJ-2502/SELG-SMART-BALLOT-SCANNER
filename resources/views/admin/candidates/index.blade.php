@extends('layouts.app')

@section('content')
<div class="card">
    <div class="actions mb-12">
        <h1 style="margin:0;">Candidates</h1>
        <div style="display:flex; gap:8px;">
            <a class="btn btn-primary" href="{{ route('candidates.create') }}">Add Candidate</a>
            <a class="btn btn-muted" href="{{ route('candidates.partylist.create') }}">Add Partylist</a>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Position</th>
                <th>Party</th>
                <th>Active</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($candidates as $candidate)
                <tr>
                    <td>{{ $candidate->id }}</td>
                    <td>{{ $candidate->name }}</td>
                    <td>{{ $candidate->position?->name }}</td>
                    <td>{{ $candidate->party }}</td>
                    <td>{{ $candidate->is_active ? 'Yes' : 'No' }}</td>
                    <td class="actions">
                        <a class="btn btn-muted" href="{{ route('candidates.edit', $candidate) }}">Edit</a>
                        <form action="{{ route('candidates.destroy', $candidate) }}" method="POST" onsubmit="return confirm('Delete this candidate?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">No candidates yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
