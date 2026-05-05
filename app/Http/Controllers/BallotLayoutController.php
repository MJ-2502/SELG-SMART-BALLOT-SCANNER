<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBallotGenerationRequest;
use App\Models\Ballot;
use App\Models\Election;
use App\Models\Position;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BallotLayoutController extends Controller
{
    public function index(Request $request): Response
    {
        $requestedElectionId = (int) $request->integer('election');

        $targetElection = null;
        if ($requestedElectionId > 0) {
            $targetElection = Election::query()
                ->withCount('ballots')
                ->find($requestedElectionId);
        }

        if (! $targetElection) {
            $targetElection = Election::query()
                ->whereIn('status', ['pending', 'active'])
                ->withCount('ballots')
                ->orderByDesc('election_date')
                ->first();
        }

        $positions = Position::query()
            ->with(['candidates' => fn ($query) => $query->where('is_active', true)->orderBy('name')->orderBy('id')])
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/BallotGenerator/Index', compact('targetElection', 'positions'));
    }

    public function generate(StoreBallotGenerationRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $requestedElectionId = isset($validated['election_id']) ? (int) $validated['election_id'] : null;
        $perSheet = 2; // Fixed layout
        $scalePercent = 100; // Fixed scale

        $result = DB::transaction(function () use ($validated, $requestedElectionId) {
            $electionQuery = Election::query()->lockForUpdate();
            if ($requestedElectionId) {
                $election = $electionQuery->find($requestedElectionId);
            } else {
                $election = $electionQuery
                    ->whereIn('status', ['pending', 'active'])
                    ->orderByDesc('election_date')
                    ->first();
            }

            if (! $election) {
                return null;
            }

            // Interpret print_count as the number of NEW ballots to generate (append),
            // not as an absolute target. This avoids accidentally overwriting or
            // reusing existing ballot numbers when the user wants to add more.
            $toGenerate = max(0, (int) $validated['print_count']);

            $existingCount = Ballot::query()
                ->where('election_id', $election->id)
                ->count();

            $nextBallotNumber = (int) (Ballot::query()
                ->where('election_id', $election->id)
                ->max('ballot_number') ?? 0) + 1;

            for ($index = 0; $index < $toGenerate; $index++) {
                Ballot::create([
                    'election_id' => $election->id,
                    'ballot_number' => $nextBallotNumber + $index,
                    'uuid' => (string) Str::uuid(),
                    'status' => 'pending',
                ]);
            }

            // Update the election's printed quantity to reflect the new total
            $newTotal = $existingCount + $toGenerate;
            $election->update([
                'ballot_print_quantity' => $newTotal,
            ]);

            return [
                'election' => $election,
                'generated' => $toGenerate,
                'existing' => $existingCount,
            ];
        });

        if (! $result) {
            return redirect()
                ->route('admin.ballot-generator.index', array_filter(['election' => $requestedElectionId]))
                ->withErrors(['target_election' => 'No target election found. Select or start an election first.']);
        }

        return redirect()
            ->route('admin.ballot-management.print', [
                'election' => $result['election']->id,
                'per_sheet' => $perSheet,
                'scale_percent' => $scalePercent,
            ])
            ->with(
                'status',
                $result['generated'] > 0
                    ? "Generated {$result['generated']} ballot(s)."
                    : 'No new ballot records were generated.'
            );
    }

    public function print(Request $request): Response
    {
        $validated = $request->validate([
            'election' => ['required', 'exists:elections,id'],
        ]);

        $perSheet = 2; // Fixed layout
        $scalePercent = 100; // Fixed scale

        $election = Election::query()->findOrFail((int) $validated['election']);

        $ballotsQuery = Ballot::query()
            ->where('election_id', $election->id)
            ->orderBy('ballot_number');

        if ($election->ballot_print_quantity > 0) {
            $ballotsQuery->limit($election->ballot_print_quantity);
        }

        $ballots = $ballotsQuery->get();

        $positions = Position::query()
            ->with(['candidates' => fn ($query) => $query->where('is_active', true)->orderBy('name')->orderBy('id')])
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/BallotManagement/Print', compact('election', 'ballots', 'positions', 'perSheet', 'scalePercent'));
    }
}
