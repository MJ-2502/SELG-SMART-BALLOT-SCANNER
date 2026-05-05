<?php

namespace App\Http\Controllers;

use App\Models\Election;
use App\Models\Report;
use App\Services\ElectionCompletionService;
use App\Services\ElectionTallyService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class ElectionResultController extends Controller
{
    public function __construct(
        private ElectionCompletionService $completionService,
        private ElectionTallyService $tallyService,
    )
    {
    }

    /**
     * Display results for a completed election
     */
    public function show(Election $election): Response
    {
        abort_if(!auth()->user()?->isAdviser(), 403, 'Adviser access only.');

        $report = Report::query()
            ->where('election_id', $election->id)
            ->latest('generated_date')
            ->first();

        if (!$report) {
            return Inertia::render('Results/NotAvailable', [
                'election' => [
                    'id' => $election->id,
                    'name' => $election->election_name,
                    'label' => $election->label,
                    'status' => $election->status,
                ],
                'message' => 'No results available yet for this election.',
            ]);
        }

        $reportData = is_array($report->report_data)
            ? $report->report_data
            : (json_decode((string) $report->report_data, true) ?: []);

        return Inertia::render('Results/Show', [
            'election' => [
                'id' => $election->id,
                'name' => $election->election_name,
                'label' => $election->label,
                'status' => $election->status,
                'date' => $election->election_date?->toDateTimeString(),
                'completed_at' => $election->completed_at?->toDateTimeString(),
            ],
            'report' => [
                'id' => $report->id,
                'generated_date' => $report->generated_date?->toDateTimeString(),
            ],
            'results' => $reportData,
        ]);
    }

    /**
     * Get election status and completion info via API
     */
    public function status(Election $election): JsonResponse
    {
        $status = $this->completionService->getElectionStatus($election);
        $tally = $this->tallyService->buildElectionSummary($election);

        return response()->json([
            'success' => true,
            'data' => $status,
            'tally' => [
                'summary' => $tally['summary'] ?? [],
                'status' => $tally['election']['status'] ?? $election->status,
            ],
        ]);
    }

    /**
     * Get winners for a specific election
     */
    public function winners(Election $election): JsonResponse
    {
        abort_if($election->status !== 'completed', 404, 'Election results not yet available.');

        $report = Report::query()
            ->where('election_id', $election->id)
            ->latest('generated_date')
            ->first();

        if (!$report) {
            return response()->json([
                'success' => false,
                'message' => 'No results found for this election.',
            ], 404);
        }

        $reportData = is_array($report->report_data)
            ? $report->report_data
            : (json_decode((string) $report->report_data, true) ?: []);

        $winners = $reportData['winners'] ?? [];

        return response()->json([
            'success' => true,
            'data' => [
                'election_id' => $election->id,
                'election_name' => $election->election_name,
                'election_label' => $election->label,
                'completed_at' => $election->completed_at?->toDateTimeString(),
                'winners' => $winners,
            ],
        ]);
    }

    /**
     * List all completed elections with their results
     */
    public function completedElections(): JsonResponse
    {
        abort_if(!auth()->user()?->isAdviser(), 403, 'Adviser access only.');

        $elections = Election::query()
            ->where('status', 'completed')
            ->with('reports')
            ->orderByDesc('completed_at')
            ->get();

        $results = $elections->map(function (Election $election) {
            $report = $election->reports()->latest('generated_date')->first();
            $reportData = $report
                ? (is_array($report->report_data)
                    ? $report->report_data
                    : (json_decode((string) $report->report_data, true) ?: []))
                : [];

            return [
                'election' => [
                    'id' => $election->id,
                    'name' => $election->election_name,
                    'label' => $election->label,
                    'status' => $election->status,
                    'date' => $election->election_date?->toDateTimeString(),
                    'completed_at' => $election->completed_at?->toDateTimeString(),
                ],
                'summary' => $reportData['summary'] ?? [],
                'winners' => $reportData['winners'] ?? [],
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $results,
        ]);
    }
}
