@extends('layouts.app')

@section('content')
<div class="card">
    <h1>Adviser Dashboard</h1>
    <p>Use this panel to manage election users, positions, and candidates.</p>
    <div class="actions">
        <a class="btn btn-primary" href="{{ route('users.index') }}">Manage Users</a>
        <a class="btn btn-primary" href="{{ route('positions.index') }}">Manage Positions</a>
        <a class="btn btn-primary" href="{{ route('candidates.index') }}">Manage Candidates</a>
    </div>
</div>
@endsection
