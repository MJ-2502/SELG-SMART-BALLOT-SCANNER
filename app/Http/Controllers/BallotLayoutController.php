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
use Illuminate\View\View;

class BallotLayoutController extends Controller
{
    public function index(): View
    {
        $activeElection = Election::query()
            ->where('status', 'active')
            ->withCount('ballots')
            ->orderByDesc('election_date')
            ->first();

        $positions = Position::query()
            ->with(['candidates' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return view('admin.ballot-generator.index', compact('activeElection', 'positions'));
    }

    public function generate(StoreBallotGenerationRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $perSheet = 2; // Fixed layout
        $scalePercent = 100; // Fixed scale

        $result = DB::transaction(function () use ($validated) {
            $election = Election::query()
                ->lockForUpdate()
                ->where('status', 'active')
                ->orderByDesc('election_date')
                ->first();

            if (! $election) {
                return null;
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

        if (! $result) {
            return redirect()
                ->route('admin.ballot-generator.index')
                ->withErrors(['active_election' => 'No active election found. Start an election first.']);
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
            ->with(['candidates' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return view('admin.ballot-generator.print', compact('election', 'ballots', 'positions', 'perSheet', 'scalePercent'));
    }
}
