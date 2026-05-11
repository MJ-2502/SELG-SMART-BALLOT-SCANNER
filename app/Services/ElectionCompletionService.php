<?php

namespace App\Services;

use App\Models\Ballot;
use App\Models\Election;
use App\Models\Report;
use App\Models\Position;
use App\Models\Candidate;
use App\Models\Vote;
use Illuminate\Support\Collection;
use App\Events\ElectionCompleted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

class ElectionCompletionService
{
    private ElectionTallyService $tallyService;

    public function __construct(ElectionTallyService $tallyService)
    {
        $this->tallyService = $tallyService;
    }

    /**
     * Check if all ballots for an election have been scanned
     */
    public function isElectionComplete(Election $election): bool
    {
        $expectedBallots = (int) ($election->ballot_print_quantity ?? 0);
        
        if ($expectedBallots === 0) {
            return false; // Cannot complete if no expected ballots set
        }

        $scannedCount = Ballot::query()
            ->where('election_id', $election->id)
            ->whereIn('status', ['scanned', 'flagged'])
            ->count();

        return $scannedCount >= $expectedBallots;
    }

    /**
     * Complete an election and generate results
     */
    public function completeElection(Election $election): ?Report
    {
        // Check if already completed
        if ($election->status === 'completed') {
            return Report::query()
                ->where('election_id', $election->id)
                ->latest('generated_date')
                ->first();
        }

        // Check if eligible for completion
        if (!$this->isElectionComplete($election)) {
            return null;
        }

        // Build the election summary
        $summary = $this->tallyService->buildElectionSummary($election);

        // Generate winners information
        $winners = $this->generateWinners($election, $summary);
        $summary['winners'] = $winners;

        $generatedDate = now();

        // Create and save the report
        $report = Report::query()->create([
            'election_id' => $election->id,
            'generated_date' => $generatedDate,
            'report_data' => $summary,
        ]);

        // Mark election as completed, but stay compatible with older schemas
        $updateData = [
            'status' => 'completed',
        ];

        if (Schema::hasColumn('elections', 'completed_at')) {
            $updateData['completed_at'] = now();
        }

        $election->update($updateData);

        // Broadcast completion event so dashboards can update in real-time
        try {
            \Log::info('Dispatching ElectionCompleted event', ['election_id' => $election->id, 'report_id' => $report->id]);
            Event::dispatch(new ElectionCompleted($election->id, $report->id));
            \Log::info('ElectionCompleted event dispatched', ['election_id' => $election->id]);
        } catch (\Throwable $ex) {
            \Log::warning('Failed to dispatch ElectionCompleted event', ['election_id' => $election->id, 'error' => $ex->getMessage()]);
            // Non-fatal: broadcasting misconfiguration should not stop completion
        }

        return $report;
    }

    /**
     * Generate winners for each position based on vote counts
     */
    public function generateWinners(Election $election, array $summary): array
    {
        $winners = [];

        // Get position tallies from summary
        $positionTallies = collect($summary['position_tallies'] ?? []);

        foreach ($positionTallies as $positionTally) {
            $positionId = $positionTally['position_id'];
            $positionName = $positionTally['position_name'];
            $votesAllowed = max(1, (int) ($positionTally['votes_allowed'] ?? 1));
            $candidates = collect($positionTally['candidates'] ?? []);

            $sortedCandidates = $candidates
                ->sortByDesc('votes')
                ->values();

            $cutoffVotes = null;
            if ($sortedCandidates->count() >= $votesAllowed) {
                $cutoffVotes = $sortedCandidates[$votesAllowed - 1]['votes'];
            }

            $aboveCutoff = $cutoffVotes === null
                ? $sortedCandidates
                : $sortedCandidates->filter(fn ($candidate) => $candidate['votes'] > $cutoffVotes)->values();

            $atCutoff = $cutoffVotes === null
                ? collect()
                : $sortedCandidates->filter(fn ($candidate) => $candidate['votes'] === $cutoffVotes)->values();

            $seatsRemaining = max(0, $votesAllowed - $aboveCutoff->count());
            $hasTie = $cutoffVotes !== null && $atCutoff->count() > $seatsRemaining;

            $topCandidates = $hasTie
                ? $aboveCutoff
                : $sortedCandidates->take($votesAllowed)->values();

            $winners[] = [
                'position_id' => $positionId,
                'position_name' => $positionName,
                'votes_allowed' => $votesAllowed,
                'winners' => $topCandidates->map(fn ($candidate) => [
                    'id' => $candidate['id'],
                    'name' => $candidate['name'],
                    'party' => $candidate['party'],
                    'votes' => (int) $candidate['votes'],
                    'color_code' => $candidate['color_code'] ?? null,
                ])->values()->all(),
                'has_tie' => $hasTie,
                'tied_vote_count' => $hasTie ? $cutoffVotes : null,
                'seats_remaining' => $hasTie ? $seatsRemaining : 0,
                'tied_candidates' => $hasTie
                    ? $atCutoff->map(fn ($candidate) => [
                        'id' => $candidate['id'],
                        'name' => $candidate['name'],
                        'party' => $candidate['party'],
                        'votes' => (int) $candidate['votes'],
                        'color_code' => $candidate['color_code'] ?? null,
                    ])->values()->all()
                    : [],
            ];
        }

        return $winners;
    }

    /**
     * Get election status summary
     */
    public function getElectionStatus(Election $election): array
    {
        $expectedBallots = (int) ($election->ballot_print_quantity ?? 0);
        $scannedCount = Ballot::query()
            ->where('election_id', $election->id)
            ->whereIn('status', ['scanned', 'flagged'])
            ->count();

        $isComplete = $this->isElectionComplete($election);
        $report = null;

        if ($isComplete && $election->status === 'completed') {
            $report = Report::query()
                ->where('election_id', $election->id)
                ->latest('generated_date')
                ->first();
        }

        return [
            'election_id' => $election->id,
            'status' => $election->status,
            'expected_ballots' => $expectedBallots,
            'scanned_ballots' => $scannedCount,
            'remaining_ballots' => max(0, $expectedBallots - $scannedCount),
            'is_complete' => $isComplete,
            'completion_percentage' => $expectedBallots > 0 
                ? (int) round(($scannedCount / $expectedBallots) * 100)
                : 0,
            'result_report_id' => $report?->id,
            'result_generated_at' => $report?->generated_date?->toDateTimeString(),
        ];
    }
}
