@extends('layouts.app')

@section('content')
<div class="ui-page">
    <div class="ui-card">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
            <div>
                <h1 class="text-xl font-semibold">Election Progress Monitoring</h1>
                <p class="text-gray-600">Track scanned ballots, validity quality, and submission throughput.</p>
            </div>
        </div>

        @if ($elections->isEmpty())
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-amber-900">
                No elections found yet. Create an election first.
            </div>
        @else
            <form method="GET" action="{{ route('admin.progress') }}" class="grid gap-3 md:grid-cols-[1fr_auto] mb-6">
                <div>
                    <label for="election" class="block text-sm font-medium mb-1">Election scope</label>
                    <select id="election" name="election" class="ui-input">
                        <option value="">All elections</option>
                        @foreach ($elections as $election)
                            <option value="{{ $election->id }}" @selected($selectedElection?->id === $election->id)>
                                {{ $election->label }} ({{ ucfirst($election->status) }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="md:self-end">
                    <button type="submit" class="ui-btn-primary w-full md:w-auto">Apply filter</button>
                </div>
            </form>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 mb-6">
                <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                    <div class="text-xs uppercase tracking-wide text-blue-700">Total Scanned Ballots</div>
                    <div class="text-3xl font-semibold text-blue-900">{{ $metrics['total_scanned'] }}</div>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                    <div class="text-xs uppercase tracking-wide text-emerald-700">Valid Submissions</div>
                    <div class="text-3xl font-semibold text-emerald-900">{{ $metrics['valid_submissions'] }}</div>
                </div>
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <div class="text-xs uppercase tracking-wide text-amber-700">Flagged Submissions</div>
                    <div class="text-3xl font-semibold text-amber-900">{{ $metrics['flagged_submissions'] }}</div>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="flex items-center justify-between mb-3 gap-2">
                    <h2 class="text-base font-semibold text-slate-900">Throughput by Time Window</h2>
                    <span class="text-xs text-slate-500">Last 8 hours (hourly)</span>
                </div>

                @php
                    $maxCount = max(1, collect($throughputWindows)->max('count') ?? 1);
                @endphp

                <div class="space-y-2">
                    @foreach ($throughputWindows as $window)
                        @php
                            $width = (int) round(($window['count'] / $maxCount) * 100);
                        @endphp
                        <div>
                            <div class="flex items-center justify-between text-sm text-slate-600 mb-1">
                                <span>{{ $window['label'] }}</span>
                                <span class="font-medium text-slate-900">{{ $window['count'] }}</span>
                            </div>
                            <div class="h-2.5 w-full rounded-full bg-slate-100 overflow-hidden">
                                <div class="h-full rounded-full bg-indigo-500" style="width: {{ $width }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
