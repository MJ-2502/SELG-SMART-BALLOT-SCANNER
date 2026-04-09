@extends('layouts.app')

@section('content')
<div class="ui-page">
    <div class="ui-card">
        <h1 class="text-xl font-semibold mb-2">Adviser Dashboard</h1>
        <p class="text-gray-600 mb-6">Use this panel to manage election users, positions, and candidates.</p>
        <div class="flex gap-2 flex-wrap">
            <a href="{{ route('users.index') }}" class="ui-btn-primary">Manage Users</a>
            <a href="{{ route('positions.index') }}" class="ui-btn-primary">Manage Positions</a>
            <a href="{{ route('candidates.index') }}" class="ui-btn-primary">Manage Candidates</a>
            <a href="{{ route('admin.ballot-layout.index') }}" class="ui-btn-primary">Ballot Layout</a>
        </div>
    </div>
</div>
@endsection
