<?php

namespace App\Http\Controllers;

use App\Models\Ballot;
use App\Models\Election;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ElectionProgressController extends Controller
{
    public function index(Request $request): Response
    {
        $elections = Election::query()
            ->orderByDesc('election_date')
            ->get(['id', 'election_name', 'election_date', 'status']);

        $selectedElectionId = $request->integer('election');
        $selectedElection = $selectedElectionId
            ? $elections->firstWhere('id', $selectedElectionId)
            : null;

        $ballotBaseQuery = Ballot::query();

        if ($selectedElection) {
            $ballotBaseQuery->where('election_id', $selectedElection->id);
        }

        $scannedQuery = (clone $ballotBaseQuery)->where('status', 'scanned');

        $totalScanned = (clone $scannedQuery)->count();
        $flaggedSubmissions = (clone $scannedQuery)
            ->where(function ($query) {
                $query->doesntHave('votes')
                    ->orWhereHas('votes', fn ($voteQuery) => $voteQuery->where('is_valid', false));
            })
            ->count();

        $metrics = [
            'total_scanned' => $totalScanned,
            'valid_submissions' => max(0, $totalScanned - $flaggedSubmissions),
            'flagged_submissions' => $flaggedSubmissions,
        ];

        $throughputWindows = $this->buildThroughputSeries($ballotBaseQuery);

        return Inertia::render('Admin/Progress', [
            'elections' => $elections,
            'selectedElection' => $selectedElection,
            'metrics' => $metrics,
            'throughputWindows' => $throughputWindows,
        ]);
    }

    private function buildThroughputSeries($ballotBaseQuery): array
    {
        $windowCount = 8;
        $windowEnd = CarbonImmutable::now()->startOfHour();
        $windowStart = $windowEnd->subHours($windowCount - 1);

        $scannedBallots = (clone $ballotBaseQuery)
            ->where('status', 'scanned')
            ->whereNotNull('scanned_at')
            ->whereBetween('scanned_at', [$windowStart, $windowEnd->addHour()])
            ->get(['scanned_at']);

        $buckets = [];
        for ($i = 0; $i < $windowCount; $i++) {
            $cursor = $windowStart->addHours($i);
            $key = $cursor->format('Y-m-d H:00:00');

            $buckets[$key] = [
                'label' => $cursor->format('M j, g A'),
                'count' => 0,
            ];
        }

        foreach ($scannedBallots as $ballot) {
            if (! $ballot->scanned_at) {
                continue;
            }

            $bucketKey = $ballot->scanned_at->copy()->startOfHour()->format('Y-m-d H:00:00');

            if (isset($buckets[$bucketKey])) {
                $buckets[$bucketKey]['count']++;
            }
        }

        return array_values($buckets);
    }
}
