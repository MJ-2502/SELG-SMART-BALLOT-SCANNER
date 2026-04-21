<?php

namespace App\Http\Controllers;

use App\Models\Ballot;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Position;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
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
            'ballots_scanned' => 0,
            'valid_ballots' => 0,
            'invalid_ballots' => 0,
            'voter_turnout' => 0,
        ];

        if ($selectedElection) {
            $scannedBallotsQuery = Ballot::query()
                ->where('election_id', $selectedElection->id)
                ->where('status', 'scanned');

            $ballotsScanned = (clone $scannedBallotsQuery)->count();

            $invalidBallots = (clone $scannedBallotsQuery)
                ->where(function ($query) {
                    $query->doesntHave('votes')
                        ->orWhereHas('votes', fn ($voteQuery) => $voteQuery->where('is_valid', false));
                })
                ->count();

            $validBallots = max(0, $ballotsScanned - $invalidBallots);

            $expectedBallots = max(0, (int) ($selectedElection->ballot_print_quantity ?? 0));
            $turnout = $expectedBallots > 0
                ? (int) round(($ballotsScanned / $expectedBallots) * 100)
                : 0;

            $stats['ballots_scanned'] = $ballotsScanned;
            $stats['valid_ballots'] = $validBallots;
            $stats['invalid_ballots'] = $invalidBallots;
            $stats['voter_turnout'] = $turnout;
        }

        return view('admin.dashboard', [
            'hasElection' => $hasElection,
            'selectedElection' => $selectedElection,
            'stats' => $stats,
        ]);
    }
}
