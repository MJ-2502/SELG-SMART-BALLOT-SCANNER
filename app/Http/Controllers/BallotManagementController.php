<?php

namespace App\Http\Controllers;

use App\Models\Ballot;
use App\Models\Election;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BallotManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $elections = Election::query()
            ->orderByDesc('election_date')
            ->withCount('ballots')
            ->get()
            ->map(function (Election $election) {
                $election->setAttribute('election_date_formatted', $election->election_date?->format('F j, Y g:i A'));

                return $election;
            });

        $selectedElectionId = (int) $request->integer('election');

        if (! $selectedElectionId) {
            $selectedElectionId = (int) ($elections->firstWhere('status', 'active')?->id ?? $elections->first()?->id ?? 0);
        }

        $selectedElection = $elections->firstWhere('id', $selectedElectionId);

        $ballots = Ballot::query()
            ->with('scanner:id,name')
            ->withCount('votes')
            ->where('election_id', $selectedElection?->id)
            ->orderBy('ballot_number')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        $ballots->setCollection(
            $ballots->getCollection()->map(function (Ballot $ballot) {
                $ballot->setAttribute('scanned_at_formatted', $ballot->scanned_at?->format('M j, Y g:i A'));

                return $ballot;
            })
        );

        $statusCounts = Ballot::query()
            ->selectRaw('status, COUNT(*) as total')
            ->where('election_id', $selectedElection?->id)
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render('Admin/BallotManagement/Index', [
            'elections' => $elections,
            'selectedElection' => $selectedElection,
            'ballots' => $ballots,
            'statusCounts' => $statusCounts,
        ]);
    }

    public function destroy(Ballot $ballot): RedirectResponse
    {
        $ballot->loadMissing('election');

        $election = $ballot->election;
        if (! $election) {
            return redirect()
                ->route('admin.ballot-management.index')
                ->with('error', 'Ballot is not linked to an election and cannot be deleted from this page.');
        }

        $isFinishedElection = $election->status === 'completed' || $election->election_date?->isPast();
        if (! $isFinishedElection) {
            return redirect()
                ->route('admin.ballot-management.index', ['election' => $election->id])
                ->with('error', 'You can only delete generated ballots from past or finished elections.');
        }

        if ($ballot->status !== 'pending') {
            return redirect()
                ->route('admin.ballot-management.index', ['election' => $ballot->election_id])
                ->with('error', 'Only pending generated ballots can be deleted.');
        }

        if ($ballot->votes()->exists()) {
            return redirect()
                ->route('admin.ballot-management.index', ['election' => $ballot->election_id])
                ->with('error', 'Cannot delete a ballot that already has votes.');
        }

        $ballotNumber = $ballot->ballot_number;
        $electionId = $ballot->election_id;

        $ballot->delete();

        return redirect()
            ->route('admin.ballot-management.index', ['election' => $electionId])
            ->with('status', "Ballot #{$ballotNumber} deleted successfully.");
    }
}
