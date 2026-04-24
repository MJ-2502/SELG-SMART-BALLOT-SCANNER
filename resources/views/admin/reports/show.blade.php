@extends('layouts.app')

@section('content')
<div class="ui-page">
    <div class="ui-card">
        <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
            <div>
                <h1 class="text-xl font-semibold">Report Details</h1>
                <p class="text-slate-600">
                    {{ $report->election?->label ?? 'Unknown election' }}
                    • Generated {{ $report->generated_date?->format('M j, Y g:i A') ?? 'N/A' }}
                </p>
            </div>
            <a href="{{ route('admin.reports.index') }}" class="ui-btn-secondary">Back to Reports</a>
        </div>

        @php
            $summary = $reportData['summary'] ?? [];
            $positionTallies = $reportData['position_tallies'] ?? [];
        @endphp

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 mb-6">
            <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                <div class="text-xs uppercase tracking-wide text-blue-700">Scanned Ballots</div>
                <div class="text-3xl font-semibold text-blue-900">{{ $summary['total_scanned'] ?? 0 }}</div>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                <div class="text-xs uppercase tracking-wide text-emerald-700">Valid Submissions</div>
                <div class="text-3xl font-semibold text-emerald-900">{{ $summary['valid_submissions'] ?? 0 }}</div>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                <div class="text-xs uppercase tracking-wide text-amber-700">Flagged Submissions</div>
                <div class="text-3xl font-semibold text-amber-900">{{ $summary['flagged_submissions'] ?? 0 }}</div>
            </div>
            <div class="rounded-xl border border-violet-200 bg-violet-50 p-4">
                <div class="text-xs uppercase tracking-wide text-violet-700">Turnout</div>
                <div class="text-3xl font-semibold text-violet-900">{{ $summary['turnout_percent'] ?? 0 }}%</div>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <h2 class="text-base font-semibold text-slate-900 mb-3">Stored Candidate Tallies</h2>

            @if (empty($positionTallies))
                <div class="text-sm text-slate-500">No candidate tally data was stored in this report.</div>
            @else
                <div class="space-y-5">
                    @foreach ($positionTallies as $position)
                        <div class="rounded-xl border border-slate-200 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                                <h3 class="font-semibold text-slate-900">{{ $position['position_name'] ?? 'Unknown position' }}</h3>
                                <span class="text-xs text-slate-500">Total votes: {{ $position['total_votes'] ?? 0 }}</span>
                            </div>

                            @if (empty($position['candidates']))
                                <p class="text-sm text-slate-500">No candidate records available for this position.</p>
                            @else
                                <div class="space-y-2">
                                    @foreach ($position['candidates'] as $candidate)
                                        <div class="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-sm">
                                            <span>
                                                {{ $candidate['name'] ?? 'Unknown candidate' }}
                                                @if (! empty($candidate['party']))
                                                    <span class="text-xs text-slate-500">({{ $candidate['party'] }})</span>
                                                @endif
                                            </span>
                                            <span class="font-semibold text-slate-900">{{ $candidate['votes'] ?? 0 }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
