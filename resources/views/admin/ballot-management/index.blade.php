@extends('layouts.app')

@section('content')
<div class="ui-page">
    <div class="ui-card">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
            <div>
                <h1 class="text-xl font-semibold">Ballot Management</h1>
                <p class="text-gray-600">Manage generated ballots by election.</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('admin.ballot-generator.index') }}" class="ui-btn-secondary">Open Ballot Generator</a>
                @if ($selectedElection)
                    <a href="{{ route('admin.ballot-generator.print', ['election' => $selectedElection->id]) }}" class="ui-btn-primary">Open Print Layout</a>
                @endif
            </div>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-emerald-900">
                {{ session('status') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-red-800">
                {{ session('error') }}
            </div>
        @endif

        @if ($elections->isEmpty())
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-amber-900">
                No elections found yet. Create an election first.
            </div>
        @else
            <form method="GET" action="{{ route('admin.ballot-management.index') }}" class="grid gap-3 md:grid-cols-[1fr_auto] mb-6">
                <div>
                    <label for="election" class="block text-sm font-medium mb-1">Election</label>
                    <select id="election" name="election" class="ui-input">
                        @foreach ($elections as $election)
                            <option value="{{ $election->id }}" @selected($selectedElection?->id === $election->id)>
                                {{ $election->label }} ({{ ucfirst($election->status) }}, Ballots: {{ $election->ballots_count }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="md:self-end">
                    <button type="submit" class="ui-btn-primary w-full md:w-auto">Load Ballots</button>
                </div>
            </form>

            @if ($selectedElection)
                <p class="text-sm text-gray-500 mb-4">
                    Delete is enabled only for pending generated ballots in past or finished elections.
                </p>
            @endif

            @if ($selectedElection)
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 mb-6">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <div class="text-xs uppercase tracking-wide text-slate-500">Total</div>
                        <div class="text-2xl font-semibold text-slate-900">{{ $ballots->total() }}</div>
                    </div>
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <div class="text-xs uppercase tracking-wide text-amber-700">Pending</div>
                        <div class="text-2xl font-semibold text-amber-900">{{ $statusCounts['pending'] ?? 0 }}</div>
                    </div>
                    <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                        <div class="text-xs uppercase tracking-wide text-blue-700">Scanned</div>
                        <div class="text-2xl font-semibold text-blue-900">{{ $statusCounts['scanned'] ?? 0 }}</div>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-100 p-4">
                        <div class="text-xs uppercase tracking-wide text-slate-600">Other</div>
                        <div class="text-2xl font-semibold text-slate-900">{{ max(0, $ballots->total() - (($statusCounts['pending'] ?? 0) + ($statusCounts['scanned'] ?? 0))) }}</div>
                    </div>
                </div>

                @if ($ballots->isEmpty())
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-slate-700">
                        No ballots found for this election.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-100 border-b">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium">Ballot #</th>
                                    <th class="px-3 py-2 text-left font-medium">UUID</th>
                                    <th class="px-3 py-2 text-left font-medium">Status</th>
                                    <th class="px-3 py-2 text-left font-medium">Votes</th>
                                    <th class="px-3 py-2 text-left font-medium">Scanned At</th>
                                    <th class="px-3 py-2 text-left font-medium">Scanned By</th>
                                    <th class="px-3 py-2 text-left font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($ballots as $ballot)
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="px-3 py-2">{{ $ballot->ballot_number ?? '-' }}</td>
                                        <td class="px-3 py-2 font-mono text-xs">{{ $ballot->uuid }}</td>
                                        <td class="px-3 py-2">
                                            @if ($ballot->status === 'pending')
                                                <span class="inline-block px-2 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">Pending</span>
                                            @elseif ($ballot->status === 'scanned')
                                                <span class="inline-block px-2 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">Scanned</span>
                                            @else
                                                <span class="inline-block px-2 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-800">{{ ucfirst($ballot->status) }}</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2">{{ $ballot->votes_count }}</td>
                                        <td class="px-3 py-2">{{ $ballot->scanned_at?->format('M j, Y g:i A') ?? '-' }}</td>
                                        <td class="px-3 py-2">{{ $ballot->scanner?->name ?? '-' }}</td>
                                        <td class="px-3 py-2">
                                            @if (($selectedElection->status === 'completed' || $selectedElection->election_date?->isPast()) && $ballot->status === 'pending' && $ballot->votes_count === 0)
                                                <form method="POST" action="{{ route('admin.ballot-management.destroy', $ballot) }}" onsubmit="return confirm('Delete this ballot?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="inline-block rounded px-2 py-1 text-xs font-semibold bg-red-500 text-white hover:bg-red-600">Delete</button>
                                                </form>
                                            @else
                                                <span class="text-xs text-slate-500">Locked</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        {{ $ballots->links() }}
                    </div>
                @endif
            @endif
        @endif
    </div>
</div>
@endsection
