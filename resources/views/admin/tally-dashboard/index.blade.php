@extends('layouts.app')

@section('content')
<div class="ui-page">
    <div class="ui-card">
        <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
            <div>
                <h1 class="text-xl font-semibold">Tally Dashboard</h1>
                <p class="text-slate-600">Live vote counts per election with chart-based visual summaries.</p>
            </div>
            <a href="{{ route('admin.reports.index') }}" class="ui-btn-secondary">Open Reports</a>
        </div>

        @if ($elections->isEmpty())
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-amber-900">
                No elections found yet. Create an election first.
            </div>
        @else
            <form method="GET" action="{{ route('admin.dashboard') }}" class="grid gap-3 md:grid-cols-[1fr_auto] mb-6">
                <div>
                    <label for="election" class="block text-sm font-medium mb-1">Election scope</label>
                    <select id="election" name="election" class="ui-input">
                        @foreach ($elections as $election)
                            <option value="{{ $election->id }}" @selected($selectedElection?->id === $election->id)>
                                {{ $election->label }} ({{ ucfirst($election->status) }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="md:self-end">
                    <button type="submit" class="ui-btn-primary w-full md:w-auto">Load tally</button>
                </div>
            </form>

            @if ($tallyData)
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 mb-6">
                    <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                        <div class="text-xs uppercase tracking-wide text-blue-700">Scanned Ballots</div>
                        <div class="text-3xl font-semibold text-blue-900">{{ $tallyData['summary']['total_scanned'] }}</div>
                    </div>
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                        <div class="text-xs uppercase tracking-wide text-emerald-700">Valid Submissions</div>
                        <div class="text-3xl font-semibold text-emerald-900">{{ $tallyData['summary']['valid_submissions'] }}</div>
                    </div>
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <div class="text-xs uppercase tracking-wide text-amber-700">Flagged Submissions</div>
                        <div class="text-3xl font-semibold text-amber-900">{{ $tallyData['summary']['flagged_submissions'] }}</div>
                    </div>
                    <div class="rounded-xl border border-violet-200 bg-violet-50 p-4">
                        <div class="text-xs uppercase tracking-wide text-violet-700">Turnout</div>
                        <div class="text-3xl font-semibold text-violet-900">{{ $tallyData['summary']['turnout_percent'] }}%</div>
                    </div>
                </div>

                <div class="grid gap-4 xl:grid-cols-2 mb-6">
                    <div class="rounded-xl border border-slate-200 bg-white p-4">
                        <h2 class="text-base font-semibold text-slate-900 mb-3">Submission Quality</h2>
                        <canvas id="submissionQualityChart" height="180"></canvas>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-4">
                        <h2 class="text-base font-semibold text-slate-900 mb-3">Top Candidates</h2>
                        <canvas id="topCandidatesChart" height="180"></canvas>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <h2 class="text-base font-semibold text-slate-900 mb-3">Position Tallies</h2>

                    <div class="space-y-5">
                        @foreach ($tallyData['position_tallies'] as $position)
                            <div class="rounded-xl border border-slate-200 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                                    <h3 class="font-semibold text-slate-900">{{ $position['position_name'] }}</h3>
                                    <span class="text-xs text-slate-500">Total votes: {{ $position['total_votes'] }}</span>
                                </div>

                                @php
                                    $maxVotes = max(1, collect($position['candidates'])->max('votes') ?? 1);
                                @endphp

                                <div class="space-y-2">
                                    @foreach ($position['candidates'] as $candidate)
                                        @php
                                            $width = (int) round(($candidate['votes'] / $maxVotes) * 100);
                                        @endphp
                                        <div>
                                            <div class="flex items-center justify-between text-sm text-slate-600 mb-1">
                                                <span>
                                                    {{ $candidate['name'] }}
                                                    @if ($candidate['party'])
                                                        <span class="text-xs text-slate-500">({{ $candidate['party'] }})</span>
                                                    @endif
                                                </span>
                                                <span class="font-medium text-slate-900">{{ $candidate['votes'] }}</span>
                                            </div>
                                            <div class="h-2.5 w-full rounded-full bg-slate-100 overflow-hidden">
                                                <div class="h-full rounded-full bg-indigo-500" style="width: {{ $width }}%"></div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @endif
    </div>
</div>

@if ($tallyData)
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script>
        const summary = @json($tallyData['summary']);
        const topCandidates = @json($tallyData['top_candidates']);

        const submissionQualityCtx = document.getElementById('submissionQualityChart');
        if (submissionQualityCtx) {
            new Chart(submissionQualityCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Valid', 'Flagged'],
                    datasets: [{
                        data: [summary.valid_submissions, summary.flagged_submissions],
                        backgroundColor: ['#16a34a', '#f59e0b'],
                        borderWidth: 0,
                    }],
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                    },
                },
            });
        }

        const topCandidatesCtx = document.getElementById('topCandidatesChart');
        if (topCandidatesCtx) {
            new Chart(topCandidatesCtx, {
                type: 'bar',
                data: {
                    labels: topCandidates.map((candidate) => candidate.name),
                    datasets: [{
                        label: 'Votes',
                        data: topCandidates.map((candidate) => candidate.votes),
                        backgroundColor: '#4f46e5',
                        borderRadius: 6,
                    }],
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false,
                        },
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0,
                            },
                        },
                    },
                },
            });
        }
    </script>
@endif
@endsection
