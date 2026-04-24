<?php

namespace App\Http\Controllers;

use App\Models\Election;
use App\Models\Report;
use App\Services\ElectionTallyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $elections = Election::query()
            ->orderByDesc('election_date')
            ->get(['id', 'election_name', 'election_date', 'status']);

        $selectedElectionId = $request->integer('election');

        $reports = Report::query()
            ->with(['election:id,election_name,election_date,status'])
            ->when($selectedElectionId, fn ($query) => $query->where('election_id', $selectedElectionId))
            ->orderByDesc('generated_date')
            ->paginate(10)
            ->withQueryString();

        return view('admin.reports.index', [
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

    public function show(Report $report): View
    {
        $report->loadMissing('election');

        $reportData = is_array($report->report_data)
            ? $report->report_data
            : (json_decode((string) $report->report_data, true) ?: []);

        return view('admin.reports.show', [
            'report' => $report,
            'reportData' => $reportData,
        ]);
    }
}
