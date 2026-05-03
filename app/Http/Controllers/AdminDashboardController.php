<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\Election;
use App\Models\Position;
use App\Services\ElectionTallyService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function index(Request $request, ElectionTallyService $tallyService): Response
    {
        // Check if an election was requested via query parameter
        $requestedElectionId = $request->integer('election');

        // Try to find the requested election first
        $selectedElection = null;
        if ($requestedElectionId > 0) {
            $selectedElection = Election::query()->find($requestedElectionId);
        }

        // If no election was requested or not found, use active or latest
        if (! $selectedElection) {
            $activeElection = Election::query()
                ->where('status', 'active')
                ->orderByDesc('election_date')
                ->first();

            $latestElection = Election::query()
                ->orderByDesc('election_date')
                ->first();

            $selectedElection = $activeElection ?? $latestElection;
        }

        $hasElection = (bool) $selectedElection;

        $stats = [
            'total_positions' => Position::query()->count(),
            'total_candidates' => Candidate::query()->count(),
        ];

        $tallyData = null;

        if ($selectedElection) {
            // Eager-load facilitators for display on the dashboard
            $selectedElection->load(['facilitators:id,name']);

            $tallyData = $tallyService->buildElectionSummary($selectedElection);
            $stats['ballots_scanned'] = $tallyData['summary']['total_scanned'];
            $stats['valid_ballots'] = $tallyData['summary']['valid_submissions'];
            $stats['invalid_ballots'] = $tallyData['summary']['flagged_submissions'];
            $stats['voter_turnout'] = $tallyData['summary']['turnout_percent'];
        }

        // Get all elections for the modal
        $availableElections = Election::query()
            ->orderByDesc('election_date')
            ->get()
            ->map(fn (Election $election) => [
                'id' => $election->id,
                'label' => $election->label,
                'election_date_formatted' => $election->election_date?->format('M d, Y'),
            ])
            ->values();

        return Inertia::render('Admin/Dashboard', [
            'hasElection' => $hasElection,
            'selectedElection' => $selectedElection,
            'stats' => $stats,
            'tallyData' => $tallyData,
            'availableElections' => $availableElections,
        ]);
    }
}
