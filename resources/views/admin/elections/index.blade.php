@extends('layouts.app')

@section('content')
<div class="ui-page">
    <div class="ui-card">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-xl font-semibold">Manage Elections</h1>
            <a href="{{ route('elections.create') }}" class="ui-btn-primary">Create Election</a>
        </div>

        @if (session('status'))
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                <p class="text-green-800">{{ session('status') }}</p>
            </div>
        @endif

        @if (session('error'))
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                <p class="text-red-800">{{ session('error') }}</p>
            </div>
        @endif

        @if ($elections->isEmpty())
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <p class="text-blue-800">No elections found. <a href="{{ route('elections.create') }}" class="font-semibold">Create one</a>.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-100 border-b">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">Election Date</th>
                            <th class="px-4 py-3 text-left font-medium">Status</th>
                            <th class="px-4 py-3 text-left font-medium">Ballots</th>
                            <th class="px-4 py-3 text-left font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($elections as $election)
                            <tr class="border-b hover:bg-gray-50">
                                <td class="px-4 py-3">{{ $election->election_date->format('F j, Y g:i A') }}</td>
                                <td class="px-4 py-3">
                                    @if ($election->status === 'active')
                                        <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">Active</span>
                                    @elseif ($election->status === 'completed')
                                        <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-800">Completed</span>
                                    @else
                                        <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">Pending</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $election->ballots_count ?? 0 }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex gap-2 flex-wrap">
                                        <a href="{{ route('admin.ballot-generator.index', ['election' => $election->id]) }}" class="inline-block px-3 py-1 rounded text-xs font-semibold bg-indigo-500 text-white hover:bg-indigo-600">Generate Ballots</a>

                                        @if ($election->status === 'pending')
                                            <form method="POST" action="{{ route('elections.start', $election) }}" style="display: inline;">
                                                @csrf
                                                <button type="submit" class="inline-block px-3 py-1 rounded text-xs font-semibold bg-green-500 text-white hover:bg-green-600">Start</button>
                                            </form>
                                        @elseif ($election->status === 'active')
                                            <form method="POST" action="{{ route('elections.stop', $election) }}" style="display: inline;">
                                                @csrf
                                                <button type="submit" class="inline-block px-3 py-1 rounded text-xs font-semibold bg-red-500 text-white hover:bg-red-600">Stop</button>
                                            </form>
                                        @endif

                                        @if ($election->status !== 'active')
                                            <form method="POST" action="{{ route('elections.destroy', $election) }}" style="display: inline;" onsubmit="return confirm('Are you sure?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-block px-3 py-1 rounded text-xs font-semibold bg-gray-400 text-white hover:bg-gray-500">Delete</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
