@extends('layouts.app')

@section('content')
<div class="ui-page">
    <div class="ui-card">
        <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
            <div>
                <h1 class="text-xl font-semibold">Election Reports</h1>
                <p class="text-slate-600">Generate and store snapshots of election tally results.</p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="ui-btn-secondary">Open Dashboard</a>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-emerald-900">
                {{ session('status') }}
            </div>
        @endif

        @if ($elections->isEmpty())
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-amber-900">
                No elections found yet. Create an election first.
            </div>
        @else
            <div class="grid gap-4 lg:grid-cols-2 mb-6">
                <form method="GET" action="{{ route('admin.reports.index') }}" class="rounded-xl border border-slate-200 p-4">
                    <h2 class="font-semibold text-slate-900 mb-3">Filter Reports</h2>
                    <label for="election" class="block text-sm font-medium mb-1">Election scope</label>
                    <select id="election" name="election" class="ui-input mb-3">
                        <option value="">All elections</option>
                        @foreach ($elections as $election)
                            <option value="{{ $election->id }}" @selected($selectedElectionId === $election->id)>
                                {{ $election->label }} ({{ ucfirst($election->status) }})
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="ui-btn-primary w-full sm:w-auto">Apply filter</button>
                </form>

                <form method="POST" action="{{ route('admin.reports.store') }}" class="rounded-xl border border-slate-200 p-4">
                    @csrf
                    <h2 class="font-semibold text-slate-900 mb-3">Generate New Report</h2>
                    <label for="election_id" class="block text-sm font-medium mb-1">Election</label>
                    <select id="election_id" name="election_id" class="ui-input mb-2" required>
                        @foreach ($elections as $election)
                            <option value="{{ $election->id }}" @selected((old('election_id') ? (int) old('election_id') : $selectedElectionId) === $election->id)>
                                {{ $election->label }} ({{ ucfirst($election->status) }})
                            </option>
                        @endforeach
                    </select>
                    @error('election_id')
                        <p class="text-sm text-red-600 mb-2">{{ $message }}</p>
                    @enderror
                    <button type="submit" class="ui-btn-primary w-full sm:w-auto">Generate report snapshot</button>
                </form>
            </div>

            <div class="rounded-xl border border-slate-200 overflow-hidden">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th class="ui-th">Generated</th>
                            <th class="ui-th">Election</th>
                            <th class="ui-th">Status</th>
                            <th class="ui-th text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($reports as $report)
                            <tr class="ui-row">
                                <td class="ui-td">{{ $report->generated_date?->format('M j, Y g:i A') ?? 'N/A' }}</td>
                                <td class="ui-td">{{ $report->election?->label ?? 'Unknown election' }}</td>
                                <td class="ui-td">
                                    @php
                                        $status = $report->election?->status;
                                    @endphp
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $status === 'active' ? 'bg-emerald-100 text-emerald-700' : ($status === 'completed' ? 'bg-slate-100 text-slate-700' : 'bg-amber-100 text-amber-700') }}">
                                        {{ ucfirst($status ?? 'unknown') }}
                                    </span>
                                </td>
                                <td class="ui-td text-right">
                                    <a href="{{ route('admin.reports.show', $report) }}" class="ui-btn-secondary ui-btn-sm">View details</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="ui-td text-center text-slate-500 py-8">No reports found for the selected filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($reports->hasPages())
                <div class="mt-4">
                    {{ $reports->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
