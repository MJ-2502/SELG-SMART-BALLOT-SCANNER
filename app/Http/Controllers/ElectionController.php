<?php

namespace App\Http\Controllers;

use App\Models\Election;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ElectionController extends Controller
{
    public function index(): View
    {
        $elections = Election::query()
            ->with('facilitators:id,name,username,grade_level')
            ->withCount('ballots')
            ->orderByDesc('election_date')
            ->get();

        $facilitators = User::query()
            ->where('role', User::ROLE_FACILITATOR)
            ->orderBy('name')
            ->get(['id', 'name', 'username', 'grade_level']);

        return view('admin.elections.index', compact('elections', 'facilitators'));
    }

    public function create(): View
    {
        $facilitators = User::query()
            ->where('role', User::ROLE_FACILITATOR)
            ->orderBy('name')
            ->get(['id', 'name', 'username', 'grade_level']);

        return view('admin.elections.create', compact('facilitators'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'election_name' => ['required', 'string', 'max:255'],
            'election_date' => ['required', 'date', 'after:now'],
            'facilitator_ids' => ['nullable', 'array'],
            'facilitator_ids.*' => [
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', User::ROLE_FACILITATOR)),
            ],
        ]);

        $election = Election::create([
            'election_name' => $validated['election_name'],
            'election_date' => $validated['election_date'],
        ]);

        $election->facilitators()->sync($validated['facilitator_ids'] ?? []);

        return redirect()
            ->route('elections.index')
            ->with('status', 'Election created successfully.');
    }

    public function assignFacilitators(Request $request, Election $election): RedirectResponse
    {
        if (!auth()->user()?->isAdviser()) {
            abort(403, 'Adviser access only.');
        }

        $validated = $request->validate([
            'facilitator_ids' => ['nullable', 'array'],
            'facilitator_ids.*' => [
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', User::ROLE_FACILITATOR)),
            ],
        ]);

        $election->facilitators()->sync($validated['facilitator_ids'] ?? []);

        return redirect()
            ->route('elections.index')
            ->with('status', 'Election facilitator assignments updated successfully.');
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
