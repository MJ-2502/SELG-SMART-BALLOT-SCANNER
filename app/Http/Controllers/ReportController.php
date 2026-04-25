<?php

namespace App\Http\Controllers;

use App\Models\Election;
use App\Models\Report;
use App\Services\ElectionTallyService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
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

        $report = Report::query()->create([
            'election_id' => $election->id,
            'generated_date' => now(),
            'report_data' => $tallyService->buildElectionSummary($election),
        ]);

        return redirect()
            ->route('admin.reports.show', $report)
            ->with('status', 'Election report generated and saved successfully.');
    }

    public function show(Report $report): Response
    {
        $report->loadMissing('election');
        $report->setAttribute('generated_date_formatted', $report->generated_date?->format('M j, Y g:i A'));

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
}
