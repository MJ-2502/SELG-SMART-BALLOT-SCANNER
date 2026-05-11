<?php

namespace App\Http\Controllers;

use App\Models\Election;
use App\Models\Report;
use App\Services\ElectionTallyService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $elections = Election::query()
            ->orderByDesc('election_date')
            ->get(['id', 'election_name', 'election_date', 'status'])
            ->map(function (Election $election) {
                $election->setAttribute('election_date_formatted', $election->election_date?->format('F j, Y g:i A'));

                return $election;
            });

        $selectedElectionId = $request->integer('election');

        $reports = Report::query()
            ->with(['election:id,election_name,election_date,status'])
            ->when($selectedElectionId, fn ($query) => $query->where('election_id', $selectedElectionId))
            ->orderByDesc('generated_date')
            ->paginate(10)
            ->withQueryString();

        $reports->setCollection(
            $reports->getCollection()->map(function (Report $report) {
                $report->setAttribute('generated_date_formatted', $report->generated_date?->format('M j, Y g:i A'));

                if ($report->relationLoaded('election') && $report->election) {
                    $report->election->setAttribute('election_date_formatted', $report->election->election_date?->format('F j, Y g:i A'));
                }

                return $report;
            })
        );

        return Inertia::render('Admin/Reports/Index', [
            'elections' => $elections,
            'selectedElectionId' => $selectedElectionId,
            'reports' => $reports,
        ]);
    }

    public function store(Request $request, ElectionTallyService $tallyService): RedirectResponse
    {
        $validated = $request->validate([
            'election_id' => ['required', 'integer', 'exists:elections,id'],
        ]);

        $election = Election::query()->findOrFail($validated['election_id']);

        $generatedDate = now();

        $report = Report::query()->create([
            'election_id' => $election->id,
            'election_label_snapshot' => $election->label,
            'generated_date' => $generatedDate,
            'report_data' => $tallyService->buildElectionSummary($election),
        ]);

        return redirect()
            ->route('admin.reports.show', $report)
            ->with('status', 'Election report generated and saved successfully.');
    }

    public function show(Report $report): Response
    {
        $report->loadMissing('election');
        $report->setAttribute(
            'generated_date_formatted',
            $report->generated_date ? $report->generated_date->format('M j, Y g:i A') : null,
        );

        if ($report->election) {
            $report->election->setAttribute('election_date_formatted', $report->election->election_date?->format('F j, Y g:i A'));
        }

        $reportData = is_array($report->report_data)
            ? $report->report_data
            : (json_decode((string) $report->report_data, true) ?: []);

        return Inertia::render('Admin/Reports/Show', [
            'report' => $report,
            'reportData' => $reportData,
        ]);
    }

    public function print(Report $report): Response
    {
        $report->loadMissing('election');
        $report->setAttribute(
            'generated_date_formatted',
            $report->generated_date ? $report->generated_date->format('M j, Y g:i A') : null,
        );

        if ($report->election) {
            $report->election->setAttribute('election_date_formatted', $report->election->election_date?->format('F j, Y g:i A'));
        }

        $reportData = is_array($report->report_data)
            ? $report->report_data
            : (json_decode((string) $report->report_data, true) ?: []);

        return Inertia::render('Admin/Reports/Print', [
            'report' => $report,
            'reportData' => $reportData,
        ]);
    }

    public function resolveTie(Request $request, Report $report): JsonResponse
    {
        abort_if(!auth()->user()?->isAdviser(), 403, 'Adviser access only.');

        $validated = $request->validate([
            'position_id' => ['required', 'integer'],
            'winner_ids' => ['required', 'array', 'min:1'],
            'winner_ids.*' => ['integer'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $reportData = is_array($report->report_data)
            ? $report->report_data
            : (json_decode((string) $report->report_data, true) ?: []);

        $winners = collect($reportData['winners'] ?? []);
        $positionIndex = $winners->search(fn ($position) => (int) ($position['position_id'] ?? 0) === (int) $validated['position_id']);

        if ($positionIndex === false) {
            return response()->json([
                'success' => false,
                'message' => 'Position not found in report winners list.',
            ], 404);
        }

        $position = $winners[$positionIndex];
        if (empty($position['has_tie'])) {
            return response()->json([
                'success' => false,
                'message' => 'This position has no unresolved tie.',
            ], 422);
        }

        $seatsRemaining = (int) ($position['seats_remaining'] ?? $position['votes_allowed'] ?? 1);
        $winnerIds = array_values(array_unique(array_map('intval', $validated['winner_ids'])));

        if (count($winnerIds) !== $seatsRemaining) {
            return response()->json([
                'success' => false,
                'message' => "Select exactly {$seatsRemaining} winner(s) to resolve this tie.",
            ], 422);
        }

        $tiedCandidates = collect($position['tied_candidates'] ?? []);
        $tiedIds = $tiedCandidates->pluck('id')->map(fn ($id) => (int) $id)->all();
        $invalidSelections = array_diff($winnerIds, $tiedIds);

        if (!empty($invalidSelections)) {
            return response()->json([
                'success' => false,
                'message' => 'One or more selected candidates are not part of the tied group.',
            ], 422);
        }

        $resolvedWinners = $tiedCandidates
            ->filter(fn ($candidate) => in_array((int) $candidate['id'], $winnerIds, true))
            ->values();

        $existingWinners = collect($position['winners'] ?? []);
        $position['winners'] = $existingWinners->merge($resolvedWinners)->values()->all();
        $position['has_tie'] = false;
        $position['seats_remaining'] = 0;
        $position['tied_candidates'] = [];
        $position['tie_resolution'] = [
            'resolved_at' => now()->toDateTimeString(),
            'resolved_by' => [
                'id' => auth()->id(),
                'name' => auth()->user()?->name,
            ],
            'note' => $validated['note'] ?? null,
            'selected_winner_ids' => $winnerIds,
            'tied_vote_count' => $position['tied_vote_count'] ?? null,
            'tied_candidates' => $tiedCandidates->values()->all(),
        ];

        $winners[$positionIndex] = $position;
        $reportData['winners'] = $winners->values()->all();
        $report->update(['report_data' => $reportData]);

        return response()->json([
            'success' => true,
            'data' => [
                'winners' => $reportData['winners'] ?? [],
            ],
        ]);
    }

    public function destroy(Report $report): RedirectResponse
    {
        // The IsAdviser middleware already protects this route, 
        // but we delete the record from the database.
        $report->delete();

        return redirect()
            ->back()
            ->with('status', 'Report deleted successfully.');
    }
}
