<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\Election;
use App\Models\Position;
use App\Services\ElectionTallyService;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(ElectionTallyService $tallyService): View
    {
        $activeElection = Election::query()
            ->where('status', 'active')
            ->orderByDesc('election_date')
            ->first();

        $latestElection = Election::query()
            ->orderByDesc('election_date')
            ->first();

        $selectedElection = $activeElection ?? $latestElection;
        $hasElection = (bool) $selectedElection;

        $stats = [
            'total_positions' => Position::query()->count(),
            'total_candidates' => Candidate::query()->count(),
        ];

        $tallyData = null;

        if ($selectedElection) {
            $tallyData = $tallyService->buildElectionSummary($selectedElection);
            $stats['ballots_scanned'] = $tallyData['summary']['total_scanned'];
            $stats['valid_ballots'] = $tallyData['summary']['valid_submissions'];
            $stats['invalid_ballots'] = $tallyData['summary']['flagged_submissions'];
            $stats['voter_turnout'] = $tallyData['summary']['turnout_percent'];
        }

        return view('admin.dashboard', [
            'hasElection' => $hasElection,
            'selectedElection' => $selectedElection,
            'stats' => $stats,
            'tallyData' => $tallyData,
        ]);
    }
}
