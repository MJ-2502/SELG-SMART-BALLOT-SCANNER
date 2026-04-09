@extends('layouts.app')

@section('content')
<div class="ui-page">
    <div class="ui-card">
        <h1 class="text-xl font-semibold mb-2">Adviser Dashboard</h1>
        <p class="text-gray-600 mb-6">Use this panel to manage elections, users, positions, and candidates.</p>
        <div class="flex gap-2 flex-wrap">
            <a href="{{ route('elections.index') }}" class="ui-btn-primary">Manage Elections</a>
            <a href="{{ route('users.index') }}" class="ui-btn-primary">Manage Users</a>
            <a href="{{ route('positions.index') }}" class="ui-btn-primary">Manage Positions</a>
            <a href="{{ route('candidates.index') }}" class="ui-btn-primary">Manage Candidates</a>
            <a href="{{ route('admin.ballot-generator.index') }}" class="ui-btn-primary">Ballot Generator</a>
            <a href="{{ route('admin.ballot-management.index') }}" class="ui-btn-primary">Ballot Management</a>
        </div>
    </div>
</div>
@endsection
