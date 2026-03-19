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
        $elections = Election::query()
            ->orderByDesc('election_date')
            ->withCount('ballots')
            ->get();

        $positions = Position::query()
            ->with(['candidates' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return view('admin.ballot-layout.index', compact('elections', 'positions'));
    }

    public function generate(StoreBallotGenerationRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $perSheet = (int) ($validated['per_sheet'] ?? 1);
        $scalePercent = (int) ($validated['scale_percent'] ?? 100);

        $result = DB::transaction(function () use ($validated) {
            $election = Election::query()
                ->lockForUpdate()
                ->findOrFail($validated['election_id']);

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

        return redirect()
            ->route('admin.ballot-layout.print', [
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
            'per_sheet' => ['nullable', 'integer', 'in:1,2,4'],
            'scale_percent' => ['nullable', 'integer', 'min:40', 'max:100'],
        ]);

        $perSheet = (int) ($validated['per_sheet'] ?? 1);
        $scalePercent = (int) ($validated['scale_percent'] ?? 100);

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

        return view('admin.ballot-layout.print', compact('election', 'ballots', 'positions', 'perSheet', 'scalePercent'));
    }
}
