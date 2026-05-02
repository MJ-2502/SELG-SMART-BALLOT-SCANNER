<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCandidateRequest;
use App\Http\Requests\StorePartylistCandidatesRequest;
use App\Http\Requests\UpdatePartylistColorRequest;
use App\Http\Requests\UpdateCandidateRequest;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Position;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CandidateController extends Controller
{
    public function index(): Response
    {
        $candidates = Candidate::query()
            ->with('position')
            ->orderBy('name')
            ->get();

        $hasElection = Election::query()->exists();

        return Inertia::render('Admin/Candidates/Index', compact('candidates', 'hasElection'));
    }

    public function create(): Response|RedirectResponse
    {
        if ($redirect = $this->redirectIfElectionMissing()) {
            return $redirect;
        }

        $positions = Position::query()
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        $colorData = $this->colorSelectionData();

        return Inertia::render('Admin/Candidates/Create', [
            'positions' => $positions,
            'colorPalette' => $colorData['palette'],
            'usedColors' => $colorData['used_colors'],
            'partyColorMap' => $colorData['party_color_map'],
        ]);
    }

    public function createPartylist(): Response|RedirectResponse
    {
        if ($redirect = $this->redirectIfElectionMissing()) {
            return $redirect;
        }

        $positions = Position::query()
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        $colorData = $this->colorSelectionData();

        return Inertia::render('Admin/Candidates/CreatePartylist', [
            'positions' => $positions,
            'colorPalette' => $colorData['palette'],
            'usedColors' => $colorData['used_colors'],
            'partyColorMap' => $colorData['party_color_map'],
        ]);
    }

    public function store(StoreCandidateRequest $request): RedirectResponse
    {
        if ($redirect = $this->redirectIfElectionMissing()) {
            return $redirect;
        }

        Candidate::create([
            'position_id' => $request->integer('position_id'),
            'name' => $request->input('name'),
            'party' => $request->input('party'),
            'color_code' => $request->input('color_code'),
            'is_active' => (bool) $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('candidates.index')
            ->with('status', 'Candidate created successfully.');
    }

    public function storePartylist(StorePartylistCandidatesRequest $request): RedirectResponse
    {
        if ($redirect = $this->redirectIfElectionMissing()) {
            return $redirect;
        }

        $party = trim((string) $request->input('party'));
        $colorCode = strtoupper((string) $request->input('color_code'));
        $isActive = (bool) $request->boolean('is_active', true);
        $entries = collect($request->input('entries', []))
            ->map(fn ($name) => trim((string) $name))
            ->filter(fn ($name) => $name !== '');

        $result = DB::transaction(function () use ($entries, $party, $isActive, $colorCode) {
            $created = 0;
            $updated = 0;

            foreach ($entries as $positionId => $candidateName) {
                $candidate = Candidate::updateOrCreate(
                    [
                        'position_id' => (int) $positionId,
                        'name' => $candidateName,
                    ],
                    [
                        'party' => $party,
                        'color_code' => $colorCode,
                        'is_active' => $isActive,
                    ],
                );

                if ($candidate->wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }
            }

            return ['created' => $created, 'updated' => $updated];
        });

        return redirect()
            ->route('candidates.index')
            ->with('status', "Partylist saved. Created: {$result['created']}, Updated: {$result['updated']}.");
    }

    public function destroyPartylist(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'party' => ['required', 'string', 'max:255'],
        ]);

        $party = trim((string) $validated['party']);
        $deleted = Candidate::query()
            ->where('party', $party)
            ->delete();

        if ($deleted === 0) {
            return redirect()
                ->route('candidates.index')
                ->with('error', 'Partylist not found or already deleted.');
        }

        return redirect()
            ->route('candidates.index')
            ->with('status', "Partylist \"{$party}\" deleted. Removed {$deleted} candidate(s).");
    }

    public function updatePartylistColor(UpdatePartylistColorRequest $request): RedirectResponse
    {
        $party = trim((string) $request->input('party'));
        $isIndependentGroup = strtolower($party) === 'independent';
        $normalizedParty = strtolower($party);
        $colorCode = strtoupper((string) $request->input('color_code'));

        $updated = Candidate::query()
            ->when(
                $isIndependentGroup,
                fn ($query) => $query->whereNull('party'),
                fn ($query) => $query
                    ->whereNotNull('party')
                    ->whereRaw('LOWER(TRIM(party)) = ?', [$normalizedParty])
            )
            ->update([
                'color_code' => $colorCode,
            ]);

        if ($updated === 0) {
            return redirect()
                ->route('candidates.index')
                ->with('error', 'Group not found or already deleted.');
        }

        return redirect()
            ->route('candidates.index')
            ->with('status', "Group \"{$party}\" color updated to {$colorCode}.");
    }

    public function edit(Candidate $candidate): Response
    {
        $positions = Position::query()
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        $colorData = $this->colorSelectionData($candidate);

        return Inertia::render('Admin/Candidates/Edit', [
            'candidate' => $candidate,
            'positions' => $positions,
            'colorPalette' => $colorData['palette'],
            'usedColors' => $colorData['used_colors'],
            'partyColorMap' => $colorData['party_color_map'],
        ]);
    }

    public function update(UpdateCandidateRequest $request, Candidate $candidate): RedirectResponse
    {
        $candidate->update([
            'position_id' => $request->integer('position_id'),
            'name' => $request->input('name'),
            'party' => $request->input('party'),
            'color_code' => $request->input('color_code'),
            'is_active' => (bool) $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('candidates.index')
            ->with('status', 'Candidate updated successfully.');
    }

    public function destroy(Candidate $candidate): RedirectResponse
    {
        $candidate->delete();

        return redirect()
            ->route('candidates.index')
            ->with('status', 'Candidate deleted successfully.');
    }

    private function redirectIfElectionMissing(): ?RedirectResponse
    {
        if (Election::query()->exists()) {
            return null;
        }

        return redirect()
            ->route('candidates.index')
            ->with('error', 'Create an election first before adding candidates or creating a partylist.');
    }

    private function colorSelectionData(?Candidate $exceptCandidate = null): array
    {
        $usedColorQuery = Candidate::query()
            ->whereNotNull('color_code');

        $partyColorQuery = Candidate::query()
            ->whereNotNull('party')
            ->whereNotNull('color_code');

        if ($exceptCandidate !== null) {
            $usedColorQuery->whereKeyNot($exceptCandidate->id);
            $partyColorQuery->whereKeyNot($exceptCandidate->id);
        }

        $usedColors = $usedColorQuery
            ->selectRaw('UPPER(color_code) as color_code')
            ->distinct()
            ->pluck('color_code')
            ->values();

        $partyColorMap = [];

        $partyColorQuery
            ->select(['party', 'color_code'])
            ->orderBy('id')
            ->get()
            ->each(function (Candidate $candidate) use (&$partyColorMap): void {
                $partyKey = strtolower(trim((string) $candidate->party));

                if ($partyKey === '' || array_key_exists($partyKey, $partyColorMap)) {
                    return;
                }

                $partyColorMap[$partyKey] = strtoupper((string) $candidate->color_code);
            });

        if ($exceptCandidate !== null && filled($exceptCandidate->party) && filled($exceptCandidate->color_code)) {
            $partyColorMap[strtolower(trim((string) $exceptCandidate->party))] = strtoupper((string) $exceptCandidate->color_code);
        }

        return [
            'palette' => config('candidate_colors.palette', []),
            'used_colors' => $usedColors,
            'party_color_map' => $partyColorMap,
        ];
    }
}
