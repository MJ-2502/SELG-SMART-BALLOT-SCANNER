@extends('layouts.app')

@section('content')
<div class="card">
    <h1>Ballot Layout</h1>
    <p>Set how many ballots should be printable for one election. The system assigns a unique ballot number per election to prevent duplication.</p>

    @if (session('status'))
        <div class="mb-12" style="color:#065f46; background:#ecfdf5; border:1px solid #a7f3d0; padding:10px; border-radius:8px;">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-12" style="color:#991b1b; background:#fef2f2; border:1px solid #fecaca; padding:10px; border-radius:8px;">
            <ul style="margin:0; padding-left:18px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($elections->isEmpty())
        <div class="mb-12" style="color:#92400e; background:#fffbeb; border:1px solid #fde68a; padding:10px; border-radius:8px;">
            No elections found. Please add at least one election record first.
        </div>
    @else
        <form action="{{ route('admin.ballot-layout.generate') }}" method="POST">
            @csrf

            <div class="mb-12">
                <label for="election_id">Election</label><br>
                <select id="election_id" name="election_id" required>
                    <option value="">Select election</option>
                    @foreach ($elections as $election)
                        <option value="{{ $election->id }}" @selected(old('election_id') == $election->id)>
                            {{ $election->label }} (Generated: {{ $election->ballots_count }})
                        </option>
                    @endforeach
                </select>
                @error('election_id') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="mb-12">
                <label for="print_count">Printable ballot count</label><br>
                <input id="print_count" type="number" name="print_count" min="1" max="5000" value="{{ old('print_count', 50) }}" required>
                <p style="margin-top:6px; color:#6b7280; font-size:14px;">
                    Example: entering 200 means this election should have 200 uniquely numbered printable ballots.
                </p>
                @error('print_count') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">Generate Ballots and Open Print Layout</button>
            </div>
        </form>
    @endif
</div>

<div class="card" style="margin-top:16px;">
    <h2 style="margin-top:0;">Current Ballot Content Preview</h2>
    <p style="margin-top:0; color:#6b7280;">This preview is based on active candidates grouped by position.</p>

    @forelse ($positions as $position)
        <div class="mb-12" style="border:1px solid #e5e7eb; border-radius:8px; padding:10px;">
            <div style="font-weight:700;">{{ $position->name }}</div>
            <ul style="margin:8px 0 0; padding-left:18px;">
                @forelse ($position->candidates as $candidate)
                    <li>{{ $candidate->name }} @if($candidate->party)({{ $candidate->party }})@endif</li>
                @empty
                    <li style="color:#6b7280;">No active candidates for this position.</li>
                @endforelse
            </ul>
        </div>
    @empty
        <p>No positions found yet.</p>
    @endforelse
</div>
@endsection
