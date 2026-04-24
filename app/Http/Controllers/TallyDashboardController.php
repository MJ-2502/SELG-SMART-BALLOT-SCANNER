<?php

namespace App\Http\Controllers;

use App\Models\Election;
use App\Services\ElectionTallyService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TallyDashboardController extends Controller
{
    public function index(Request $request, ElectionTallyService $tallyService): View
    {
        $elections = Election::query()
            ->orderByDesc('election_date')
            ->get(['id', 'election_name', 'election_date', 'status']);

        $selectedElectionId = $request->integer('election');

        if ($selectedElectionId) {
            $selectedElection = $elections->firstWhere('id', $selectedElectionId);
        } else {
            $selectedElection = $elections->firstWhere('status', 'active') ?? $elections->first();
        }

        $selectedElection = $selectedElection
            ? Election::query()->find($selectedElection->id)
            : null;

        $tallyData = $selectedElection
            ? $tallyService->buildElectionSummary($selectedElection)
            : null;

        return view('admin.tally-dashboard.index', [
            'elections' => $elections,
            'selectedElection' => $selectedElection,
            'tallyData' => $tallyData,
        ]);
    }
}
