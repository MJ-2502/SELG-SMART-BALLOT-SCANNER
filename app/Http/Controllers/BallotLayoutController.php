<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBallotGenerationRequest;
use App\Models\Ballot;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Position;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BallotLayoutController extends Controller
{
    public function index(Request $request): View
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
                ->where('status', 'active')
                ->withCount('ballots')
                ->orderByDesc('election_date')
                ->first();
        }

        $positions = Position::query()
            ->with(['candidates' => fn ($query) => $query->where('is_active', true)->orderBy('name')->orderBy('id')])
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return view('admin.ballot-generator.index', compact('targetElection', 'positions'));
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
                    ->where('status', 'active')
                    ->orderByDesc('election_date')
                    ->first();
            }

            if (! $election) {
                return [
                    'error' => 'No target election found. Select or start an election first.',
                    'election_id' => $requestedElectionId,
                ];
            }

            $hasActiveCandidates = Candidate::query()
                ->where('is_active', true)
                ->exists();

            $hasActivePartylist = Candidate::query()
                ->where('is_active', true)
                ->whereNotNull('party')
                ->whereRaw("TRIM(party) <> ''")
                ->exists();

            if (! $hasActiveCandidates || ! $hasActivePartylist) {
                $missing = [];
                if (! $hasActiveCandidates) {
                    $missing[] = 'candidates';
                }
                if (! $hasActivePartylist) {
                    $missing[] = 'partylist';
                }

                $missingLabel = implode(' and ', $missing);

                return [
                    'error' => "Before generating ballots for this election, add active {$missingLabel} first in Candidate Management.",
                    'election_id' => $election->id,
                ];
            }

            $targetCount = (int) $validated['print_count'];
            $existingCount = Ballot::query()
                ->where('election_id', $election->id)
                ->count();

            $nextBallotNumber = (int) (Ballot::query()
                ->where('election_id', $election->id)
                ->max('ballot_number') ?? 0) + 1;

            $toGenerate = max(0, $targetCount - $existingCount);

            for ($index = 0; $index < $toGenerate; $index++) {
                Ballot::create([
                    'election_id' => $election->id,
                    'ballot_number' => $nextBallotNumber + $index,
                    'uuid' => (string) Str::uuid(),
                    'status' => 'pending',
                ]);
            }

            $election->update([
                'ballot_print_quantity' => $targetCount,
            ]);

            return [
                'election' => $election,
                'generated' => $toGenerate,
                'existing' => $existingCount,
            ];
        });

        if (isset($result['error'])) {
            return redirect()
                ->route('admin.ballot-generator.index', array_filter(['election' => $result['election_id'] ?? $requestedElectionId]))
                ->withErrors(['target_election' => $result['error']]);
        }

        return redirect()
            ->route('admin.ballot-generator.print', [
                'election' => $result['election']->id,
                'per_sheet' => $perSheet,
                'scale_percent' => $scalePercent,
            ])
            ->with(
                'status',
                $result['generated'] > 0
                    ? "Generated {$result['generated']} ballot(s)."
                    : 'No new ballot records were generated. Existing ballot count already meets the target.'
            );
    }

    public function print(Request $request): View
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

        return view('admin.ballot-generator.print', compact('election', 'ballots', 'positions', 'perSheet', 'scalePercent'));
    }
}
