@extends('layouts.app')

@section('content')
<div class="ui-page">
    @if (! $hasElection)
        <div class="ui-card relative overflow-hidden">
            <div class="pointer-events-none absolute -right-16 -top-16 h-40 w-40 rounded-full bg-indigo-300/30 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-20 left-10 h-44 w-44 rounded-full bg-sky-300/30 blur-3xl"></div>
            <div class="relative max-w-3xl">
                <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-slate-900 mb-2">Welcome to SELG Ballot Scanner</h1>
                <p class="text-slate-600 mb-8">No election has been created yet. Start by creating your first election to unlock live monitoring and progress insights.</p>

                <div class="rounded-2xl border border-indigo-100 bg-white/90 p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-900 mb-2">Ready to begin?</h2>
                    <p class="text-sm text-slate-600 mb-5">Set up election details first, then ballot generation and scanner workflows will follow automatically.</p>
                    <a href="{{ route('elections.create') }}" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
                       Start / Create Election
                    </a>
                </div>
            </div>
        </div>
    @else
        <div class="ui-card">
            <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-slate-900">Dashboard</h1>
                    <p class="text-slate-600 mt-1">
                        Monitoring for {{ $selectedElection->label }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.progress', ['election' => $selectedElection->id]) }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Live Monitoring
                    </a>
                    <a href="{{ route('admin.reports.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Reports
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 mb-6">
                <div class="rounded-2xl border border-slate-200 bg-white/90 p-5 shadow-sm">
                    <div class="text-sm text-slate-500 mb-1">Total Positions</div>
                    <div class="text-3xl font-semibold text-slate-900">{{ $stats['total_positions'] }}</div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white/90 p-5 shadow-sm">
                    <div class="text-sm text-slate-500 mb-1">Total Candidates</div>
                    <div class="text-3xl font-semibold text-slate-900">{{ $stats['total_candidates'] }}</div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white/90 p-5 shadow-sm">
                    <div class="text-sm text-slate-500 mb-1">Ballots Scanned</div>
                    <div class="text-3xl font-semibold text-slate-900">{{ $stats['ballots_scanned'] }}</div>
                </div>

                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
                    <div class="text-sm text-emerald-700 mb-1">Valid Ballots</div>
                    <div class="text-3xl font-semibold text-emerald-900">{{ $stats['valid_ballots'] }}</div>
                </div>

                <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 shadow-sm">
                    <div class="text-sm text-rose-700 mb-1">Invalid Ballots</div>
                    <div class="text-3xl font-semibold text-rose-900">{{ $stats['invalid_ballots'] }}</div>
                </div>

                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
                    <div class="text-sm text-amber-700 mb-1">Voter Turnout</div>
                    <div class="text-3xl font-semibold text-amber-900">{{ $stats['voter_turnout'] }}%</div>
                </div>
            </div>

            @if ($tallyData)
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

                    <div class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
                        @foreach ($tallyData['position_tallies'] as $position)
                            <div class="rounded-xl border border-slate-200 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                                    <h3 class="font-semibold text-slate-900">{{ $position['position_name'] }}</h3>
                                    <span class="text-xs text-slate-500">Total votes: {{ $position['total_votes'] }}</span>
                                </div>
                                <canvas id="positionChart{{ $loop->index }}" height="220"></canvas>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>

@if ($tallyData)
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script>
        const summary = @json($tallyData['summary']);
        const topCandidates = @json($tallyData['top_candidates']);
        const positionTallies = @json($tallyData['position_tallies']);

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

        positionTallies.forEach((position, index) => {
            const positionChartCtx = document.getElementById(`positionChart${index}`);

            if (!positionChartCtx) {
                return;
            }

            new Chart(positionChartCtx, {
                type: 'bar',
                data: {
                    labels: position.candidates.map((candidate) => candidate.name),
                    datasets: [{
                        label: 'Votes',
                        data: position.candidates.map((candidate) => candidate.votes),
                        backgroundColor: '#2563eb',
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
        });
    </script>
@endif
@endsection
