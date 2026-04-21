@extends('layouts.app')

@section('content')
<div class="ui-page">
    <div class="ui-card relative overflow-hidden">
        <div class="pointer-events-none absolute -right-16 -top-16 h-40 w-40 rounded-full bg-indigo-300/30 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-20 left-10 h-44 w-44 rounded-full bg-sky-300/30 blur-3xl"></div>
        <div class="relative">
            @if (! $hasElection)
                <div class="max-w-3xl">
                    <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-slate-900 mb-2">Welcome to SELG Ballot Scanner</h1>
                    <p class="text-slate-600 mb-8">No election has been created yet. Start by creating your first election to unlock live monitoring and progress insights.</p>

                    <div class="rounded-2xl border border-indigo-100 bg-white/90 p-6 shadow-sm">
                        <h2 class="text-lg font-semibold text-slate-900 mb-2">Ready to begin?</h2>
                        <p class="text-sm text-slate-600 mb-5">Set up election details first, then ballot generation and scanner workflows will follow automatically.</p>
                        <a href="{{ route('elections.create') }}" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
                            Create Election
                        </a>
                    </div>
                </div>
            @else
                <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-slate-900">Dashboard</h1>
                        <p class="text-slate-600 mt-1">
                            Monitoring for {{ $selectedElection->label }}
                        </p>
                    </div>
                    <a href="{{ route('admin.progress', ['election' => $selectedElection->id]) }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Open Live Monitoring
                    </a>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
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
            @endif
        </div>
    </div>
</div>
@endsection
