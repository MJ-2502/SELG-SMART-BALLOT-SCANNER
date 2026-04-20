@extends('layouts.app')

@section('content')
<div class="ui-page">
    <div class="ui-card relative overflow-hidden">
        <div class="pointer-events-none absolute -right-16 -top-16 h-40 w-40 rounded-full bg-indigo-300/30 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-20 left-10 h-44 w-44 rounded-full bg-sky-300/30 blur-3xl"></div>

        <div class="relative">
            <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-slate-900 mb-2">Adviser Dashboard</h1>
            <p class="text-slate-600 mb-8">Manage your election lifecycle with quick actions for setup, validation, and ballot operations.</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <a href="{{ route('elections.index') }}" class="group rounded-2xl border border-indigo-100 bg-white/85 p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg">
                    <div class="mb-4 inline-flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 3.75h12A2.25 2.25 0 0 1 20.25 6v12A2.25 2.25 0 0 1 18 20.25H6A2.25 2.25 0 0 1 3.75 18V6A2.25 2.25 0 0 1 6 3.75Zm.75 5.25h10.5v1.5H6.75V9Zm0 3.75h10.5v1.5H6.75v-1.5Zm0 3.75h6v1.5h-6v-1.5Z" /></svg>
                    </div>
                    <h3 class="font-semibold text-slate-900">Manage Elections</h3>
                    <p class="mt-1 text-sm text-slate-600">Create and configure election schedules and settings.</p>
                </a>

                <a href="{{ route('users.index') }}" class="group rounded-2xl border border-indigo-100 bg-white/85 p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg">
                    <div class="mb-4 inline-flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M15 8.25a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm-7.5 9a6 6 0 1 1 12 0v.75h-12v-.75Z" /></svg>
                    </div>
                    <h3 class="font-semibold text-slate-900">Manage Users</h3>
                    <p class="mt-1 text-sm text-slate-600">Control adviser and student access roles.</p>
                </a>

                <a href="{{ route('positions.index') }}" class="group rounded-2xl border border-indigo-100 bg-white/85 p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg">
                    <div class="mb-4 inline-flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M5.25 5.25h13.5v3H5.25v-3Zm0 5.25h13.5v3H5.25v-3Zm0 5.25h13.5v3H5.25v-3Z" /></svg>
                    </div>
                    <h3 class="font-semibold text-slate-900">Manage Positions</h3>
                    <p class="mt-1 text-sm text-slate-600">Define ballot positions and ordering rules.</p>
                </a>

                <a href="{{ route('candidates.index') }}" class="group rounded-2xl border border-indigo-100 bg-white/85 p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg">
                    <div class="mb-4 inline-flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 3.75a4.5 4.5 0 1 1 0 9 4.5 4.5 0 0 1 0-9Zm-7.5 15a7.5 7.5 0 1 1 15 0v1.5h-15v-1.5Z" /></svg>
                    </div>
                    <h3 class="font-semibold text-slate-900">Manage Candidates</h3>
                    <p class="mt-1 text-sm text-slate-600">Register and maintain candidate records.</p>
                </a>

                <a href="{{ route('admin.ballot-generator.index') }}" class="group rounded-2xl border border-indigo-100 bg-white/85 p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg">
                    <div class="mb-4 inline-flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 3.75h8.25L18 7.5v12.75H6V3.75Zm7.5.44V8.25h4.06l-4.06-4.06ZM8.25 11.25h7.5v1.5h-7.5v-1.5Zm0 3.75h7.5v1.5h-7.5V15Z" /></svg>
                    </div>
                    <h3 class="font-semibold text-slate-900">Ballot Generator</h3>
                    <p class="mt-1 text-sm text-slate-600">Generate printable ballots with current election data.</p>
                </a>

                <a href="{{ route('admin.ballot-management.index') }}" class="group rounded-2xl border border-indigo-100 bg-white/85 p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg">
                    <div class="mb-4 inline-flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3.75 6A2.25 2.25 0 0 1 6 3.75h12A2.25 2.25 0 0 1 20.25 6v12A2.25 2.25 0 0 1 18 20.25H6A2.25 2.25 0 0 1 3.75 18V6Zm4.5 2.25h7.5v1.5h-7.5v-1.5Zm0 3.75h7.5v1.5h-7.5V12Zm0 3.75H12v1.5H8.25v-1.5Z" /></svg>
                    </div>
                    <h3 class="font-semibold text-slate-900">Ballot Management</h3>
                    <p class="mt-1 text-sm text-slate-600">Track templates, revisions, and ballot publishing.</p>
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
