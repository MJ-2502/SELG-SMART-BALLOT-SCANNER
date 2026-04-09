<?php

namespace App\Http\Controllers;

use App\Models\Election;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ElectionController extends Controller
{
    public function index(): View
    {
        $elections = Election::query()
            ->orderByDesc('election_date')
            ->get();

        return view('admin.elections.index', compact('elections'));
    }

    public function create(): View
    {
        return view('admin.elections.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'election_date' => ['required', 'date', 'after:now'],
        ]);

        Election::create($validated);

        return redirect()
            ->route('elections.index')
            ->with('status', 'Election created successfully.');
    }

    public function start(Election $election): RedirectResponse
    {
        // Verify only adviser can start elections
        if (!auth()->user()?->isAdviser()) {
            abort(403, 'Adviser access only.');
        }

        // Ensure an active election doesn't already exist
        $activeElection = Election::query()
            ->where('status', 'active')
            ->where('id', '!=', $election->id)
            ->first();

        if ($activeElection) {
            return redirect()
                ->route('elections.index')
                ->with('error', "Election on {$activeElection->election_date->format('F j, Y')} is already active. Stop it first.");
        }

        // Use transaction to atomically update status
        DB::transaction(function () use ($election) {
            // Stop any currently active election
            Election::query()
                ->where('status', 'active')
                ->where('id', '!=', $election->id)
                ->update(['status' => 'completed']);

            // Start this election
            $election->update(['status' => 'active']);
        });

        return redirect()
            ->route('elections.index')
            ->with('status', "Election on {$election->election_date->format('F j, Y')} has been started.");
    }

    public function stop(Election $election): RedirectResponse
    {
        // Verify only adviser can stop elections
        if (!auth()->user()?->isAdviser()) {
            abort(403, 'Adviser access only.');
        }

        if ($election->status !== 'active') {
            return redirect()
                ->route('elections.index')
                ->with('error', 'Only active elections can be stopped.');
        }

        $election->update(['status' => 'completed']);

        return redirect()
            ->route('elections.index')
            ->with('status', "Election on {$election->election_date->format('F j, Y')} has been stopped.");
    }

    public function destroy(Election $election): RedirectResponse
    {
        // Verify only adviser can delete elections
        if (!auth()->user()?->isAdviser()) {
            abort(403, 'Adviser access only.');
        }

        // Prevent deletion of active elections
        if ($election->status === 'active') {
            return redirect()
                ->route('elections.index')
                ->with('error', 'Cannot delete an active election. Stop it first.');
        }

        $electionDate = $election->election_date->format('F j, Y');
        $deletedBallots = 0;

        DB::transaction(function () use ($election, &$deletedBallots) {
            $deletedBallots = $election->ballots()->count();

            // Deleting ballots also cascades related votes via foreign key constraints.
            $election->ballots()->delete();
            $election->delete();
        });

        return redirect()
            ->route('elections.index')
            ->with('status', "Election on {$electionDate} and {$deletedBallots} ballot(s) have been deleted.");
    }
}
