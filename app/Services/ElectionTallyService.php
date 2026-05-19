<?php

namespace App\Services;

use App\Models\Ballot;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Position;
use App\Models\Vote;

class ElectionTallyService
{
    public function buildElectionSummary(Election $election): array
    {
        $scannedBallotsQuery = Ballot::query()
            ->where('election_id', $election->id)
            ->whereIn('status', ['scanned', 'flagged']);

        $totalScanned = (clone $scannedBallotsQuery)->count();

        $flaggedSubmissions = (clone $scannedBallotsQuery)
            ->where(function ($query) {
                $query->whereHas('flags')
                    ->orWhereDoesntHave('votes')
                    ->orWhereHas('votes', fn ($voteQuery) => $voteQuery->where('is_valid', false));
            })
            ->count();

        $validSubmissions = max(0, $totalScanned - $flaggedSubmissions);
        $expectedBallots = Ballot::query()
            ->where('election_id', $election->id)
            ->count();
        $turnout = $expectedBallots > 0
            ? (int) round(($totalScanned / $expectedBallots) * 100)
            : 0;

        $candidateVoteCounts = Vote::query()
            ->selectRaw('votes.candidate_id, COUNT(*) as total_votes')
            ->join('ballots', 'ballots.id', '=', 'votes.ballot_id')
            ->where('ballots.election_id', $election->id)
            ->whereIn('ballots.status', ['scanned', 'flagged'])
            ->where('votes.is_valid', true)
            ->whereNotNull('votes.candidate_id')
            ->groupBy('votes.candidate_id')
            ->pluck('total_votes', 'votes.candidate_id');

        $ballotsWithVotesByPosition = Vote::query()
            ->selectRaw('votes.position_id, COUNT(DISTINCT votes.ballot_id) as ballots_with_votes')
            ->join('ballots', 'ballots.id', '=', 'votes.ballot_id')
            ->where('ballots.election_id', $election->id)
            ->whereIn('ballots.status', ['scanned', 'flagged'])
            ->where('votes.is_valid', true)
            ->whereNotNull('votes.candidate_id')
            ->groupBy('votes.position_id')
            ->pluck('ballots_with_votes', 'votes.position_id');

        $positions = Position::query()
            ->with([
                'candidates' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->orderBy('id'),
            ])
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        $positionTallies = [];
        $flattenedCandidates = collect();

        foreach ($positions as $position) {
            $candidateRows = $position->candidates
                ->map(function (Candidate $candidate) use ($candidateVoteCounts, $position) {
                    return [
                        'id' => $candidate->id,
                        'name' => $candidate->name,
                        'party' => $candidate->party,
                        'color_code' => $candidate->color_code,
                        'position_id' => $position->id,
                        'position_name' => $position->name,
                        'votes' => (int) ($candidateVoteCounts[$candidate->id] ?? 0),
                    ];
                })
                ->sortByDesc('votes')
                ->values();

            $totalPositionVotes = (int) $candidateRows->sum('votes');
            $votesAllowed = max(1, (int) ($position->votes_allowed ?? 1));
            $ballotsWithVotes = (int) ($ballotsWithVotesByPosition[$position->id] ?? 0);
            $noVoteCount = max(0, $totalScanned - $ballotsWithVotes);

            $positionTallies[] = [
                'position_id' => $position->id,
                'position_name' => $position->name,
                'votes_allowed' => $votesAllowed,
                'total_votes' => $totalPositionVotes,
                'no_vote_count' => $noVoteCount,
                'candidates' => $candidateRows->all(),
            ];

            $flattenedCandidates = $flattenedCandidates->merge($candidateRows);
        }

        $topCandidates = $flattenedCandidates
            ->sortByDesc('votes')
            ->take(10)
            ->values();

        return [
            'election' => [
                'id' => $election->id,
                'name' => $election->election_name,
                'label' => $election->label,
                'status' => $election->status,
                'date' => $election->election_date?->toDateTimeString(),
            ],
            'summary' => [
                'total_scanned' => $totalScanned,
                'valid_submissions' => $validSubmissions,
                'flagged_submissions' => $flaggedSubmissions,
                'expected_ballots' => $expectedBallots,
                'turnout_percent' => $turnout,
            ],
            'position_tallies' => $positionTallies,
            'top_candidates' => $topCandidates->all(),
            'generated_at' => now()->toDateTimeString(),
        ];
    }
}
