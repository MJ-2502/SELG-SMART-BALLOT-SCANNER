<?php

namespace App\Http\Controllers;

use App\Models\Ballot;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Vote;
use App\Models\Position;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ScannerController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();

        $electionsQuery = Election::query()->orderByDesc('election_date');

        if (! $user?->isAdviser()) {
            $electionsQuery->where(function ($query) use ($user) {
                $query->whereHas('facilitators', fn ($facilitatorQuery) => $facilitatorQuery->where('users.id', $user?->id))
                    ->orWhere('facilitator_id', $user?->id);
            });
        }

        $elections = $electionsQuery->get();

        $positions = Position::query()
            ->with(['candidates' => fn ($query) => $query->where('is_active', true)->orderBy('name')->orderBy('id')])
            ->whereHas('candidates', fn ($query) => $query->where('is_active', true))
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        $positionVoteLimits = $positions->mapWithKeys(function ($position) {
            return [$position->id => max(1, (int) ($position->votes_allowed ?? 1))];
        });

        return Inertia::render('Scanner/Index', [
            'elections' => $elections->map(fn (Election $election) => [
                'id' => $election->id,
                'label' => $election->label,
            ])->values(),
            'positions' => $positions->map(fn (Position $position) => [
                'id' => $position->id,
                'name' => $position->name,
                'votes_allowed' => max(1, (int) ($position->votes_allowed ?? 1)),
                'candidates' => $position->candidates->map(fn (Candidate $candidate) => [
                    'id' => $candidate->id,
                    'name' => $candidate->name,
                ])->values(),
            ])->values(),
            'serviceUrl' => config('omr.service_url'),
            'layoutCount' => $this->buildBallotLayout($positions)->count(),
            'scanUrl' => route('scanner.scan'),
            'submitUrl' => route('scanner.submit'),
        ]);
    }

    public function scan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ballot_image' => ['required', 'file', 'image', 'max:10240'],
            'election_id' => ['nullable', 'integer', 'exists:elections,id'],
            'ballot_number' => ['nullable', 'string', 'max:255'],
        ]);

        if (! empty($validated['election_id']) && ! $this->canScanElection((int) $validated['election_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'You are not assigned to scan ballots for the selected election.',
                'errors' => ['Election access denied.'],
            ], 403);
        }

        $positions = Position::query()
            ->with(['candidates' => fn ($query) => $query->where('is_active', true)->orderBy('name')->orderBy('id')])
            ->whereHas('candidates', fn ($query) => $query->where('is_active', true))
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        $positionVoteLimits = $positions->mapWithKeys(fn ($position) => [
            $position->id => max(1, (int) ($position->votes_allowed ?? 1)),
        ]);

        $ballotLayout = $this->buildBallotLayout($positions);

        if ($ballotLayout->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No active candidates are configured yet.',
                'detected_votes' => [],
                'image_quality' => 0,
                'markers_detected' => 0,
                'processing_time_ms' => 0,
                'errors' => ['Configure positions and active candidates before scanning.'],
            ], 422);
        }

        $image = $request->file('ballot_image');
        $imageContents = (string) file_get_contents($image->getRealPath());
        $base64Image = base64_encode($imageContents);
        $mimeType = $image->getMimeType() ?: 'image/jpeg';
        $imageHash = hash('sha256', $imageContents);

        if (Ballot::query()->where('image_hash', $imageHash)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This ballot image was already scanned and saved.',
                'errors' => ['Duplicate ballot detected by image hash.'],
                'scan_preview' => [
                    'can_submit' => false,
                    'image_hash' => $imageHash,
                    'detected_votes' => [],
                    'warnings' => ['Duplicate ballot detected.'],
                ],
            ], 409);
        }

        $payload = [
            'ballot_image_base64' => "data:{$mimeType};base64,{$base64Image}",
            'ballot_layout' => $ballotLayout->values()->all(),
            'election_id' => $validated['election_id'] ?? null,
            'ballot_number' => $validated['ballot_number'] ?? null,
        ];

        $serviceUrl = rtrim((string) config('omr.service_url'), '/');
        $timeout = (int) config('omr.timeout', 30);
        
        // Check if debug data is requested (default: false for clean responses)
        $includeDebugImage = filter_var($request->query('include_debug_image'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
        $includeDebugBubbles = filter_var($request->query('include_debug_bubbles'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;

        try {
            $httpClient = Http::acceptJson()->timeout($timeout);
            
            if ($includeDebugImage || $includeDebugBubbles) {
                $httpClient = $httpClient->withQueryParameters([
                    'include_debug_image' => $includeDebugImage ? 'true' : 'false',
                    'include_debug_bubbles' => $includeDebugBubbles ? 'true' : 'false',
                ]);
            }
            
            $response = $httpClient->post($serviceUrl . '/scan', $payload);
        } catch (\Throwable $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to reach the OMR service.',
                'errors' => [$exception->getMessage()],
            ], 502);
        }

        if (! $response->successful()) {
            return response()->json([
                'success' => false,
                'message' => 'The OMR service returned an error response.',
                'errors' => [
                    $response->json('detail') ?? $response->body(),
                ],
            ], $response->status());
        }

        $responseData = $response->json();
        $detectedVotes = collect($responseData['detected_votes'] ?? []);
        $activeCandidates = Candidate::query()
            ->where('is_active', true)
            ->get(['id', 'position_id', 'name', 'party'])
            ->keyBy('id');
        $positionNames = Position::query()->get(['id', 'name'])->keyBy('id');

        $warnings = [];
        $validVotes = [];
        $normalizedVotes = [];
        $minimumConfidence = (float) config('omr.minimum_confidence', 0.34);
        $minimumConfidenceMulti = (float) config('omr.minimum_confidence_multi', 0.18);
        $minimumGapSingleSeat = (float) config('omr.minimum_gap_single_seat', 0.05);

        foreach ($detectedVotes as $vote) {
            $candidateId = (int) ($vote['candidate_id'] ?? 0);
            $positionId = (int) ($vote['position_id'] ?? 0);

            if (! $candidateId || ! $positionId) {
                $warnings[] = 'A detected vote is missing candidate or position metadata.';
                continue;
            }

            $candidate = $activeCandidates->get($candidateId);
            if (! $candidate) {
                $warnings[] = "Candidate {$candidateId} is not active or does not exist.";
                continue;
            }

            if ((int) $candidate->position_id !== $positionId) {
                $warnings[] = "Candidate {$candidateId} does not belong to position {$positionId}.";
                continue;
            }

            $normalizedVotes[] = [
                'position_id' => $positionId,
                'candidate_id' => $candidateId,
                'confidence' => (float) ($vote['confidence'] ?? 0),
                'candidate_name' => $vote['candidate_name'] ?? $candidate->name,
                'candidate_party' => $vote['candidate_party'] ?? $candidate->party,
                'position_name' => $vote['position_name'] ?? optional($positionNames->get($positionId))->name,
                'row' => $vote['row'] ?? null,
                'col' => $vote['col'] ?? null,
            ];
        }

        $votesPerPosition = collect($normalizedVotes)->groupBy('position_id');

        foreach ($votesPerPosition as $positionId => $votes) {
            $positionId = (int) $positionId;
            $positionLimit = (int) ($positionVoteLimits->get($positionId) ?? 1);

            $dedupedByCandidate = $votes
                ->sortByDesc('confidence')
                ->unique('candidate_id')
                ->values();

            if ($positionLimit === 1) {
                $strongVotes = $dedupedByCandidate
                    ->filter(fn ($vote) => (float) ($vote['confidence'] ?? 0) >= $minimumConfidence)
                    ->values();

                if ($strongVotes->isEmpty()) {
                    if ($dedupedByCandidate->isNotEmpty()) {
                        $warnings[] = "Position {$positionId} has no mark above confidence threshold {$minimumConfidence}.";
                    }
                    continue;
                }

                $topVote = $strongVotes->first();
                $runnerUp = $strongVotes->skip(1)->first();
                $gap = $runnerUp ? ((float) $topVote['confidence'] - (float) $runnerUp['confidence']) : 1.0;

                if ((float) $topVote['confidence'] < 0.55 && $runnerUp && $gap < $minimumGapSingleSeat) {
                    $warnings[] = "Position {$positionId} has ambiguous marks; no candidate was auto-selected.";
                    continue;
                }

                $selectedVotes = collect([$topVote]);
            } else {
                // For multi-vote positions, trust OMR ranking and use a lighter confidence floor.
                $selectedVotes = $dedupedByCandidate
                    ->filter(fn ($vote) => (float) ($vote['confidence'] ?? 0) >= $minimumConfidenceMulti)
                    ->take($positionLimit)
                    ->values();

                if ($selectedVotes->isEmpty() && $dedupedByCandidate->isNotEmpty()) {
                    $selectedVotes = $dedupedByCandidate->take($positionLimit)->values();
                    $warnings[] = "Position {$positionId} used fallback rank selection due low confidence signals.";
                }
            }

            $ignoredCount = $dedupedByCandidate->count() - $selectedVotes->count();

            if ($ignoredCount > 0) {
                $warnings[] = "Position {$positionId} allows up to {$positionLimit} vote(s); {$ignoredCount} lower-confidence mark(s) were ignored.";
            }

            foreach ($selectedVotes as $vote) {
                $validVotes[] = $vote;
            }
        }

        $responseData['scan_preview'] = [
            'can_submit' => count($validVotes) > 0,
            'image_hash' => $imageHash,
            'detected_votes' => $validVotes,
            'warnings' => $warnings,
        ];

        // Keep API top-level votes aligned with preview so UI shows only filtered, likely-shaded candidates.
        $responseData['detected_votes'] = $validVotes;

        return response()->json($responseData);
    }

    public function submit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image_hash' => ['required', 'string', 'size:64'],
            'election_id' => ['nullable', 'integer', 'exists:elections,id'],
            'ballot_number' => ['nullable', 'integer', 'min:1'],
            'detected_votes' => ['required', 'array', 'min:1'],
            'detected_votes.*.position_id' => ['required', 'integer', 'exists:positions,id'],
            'detected_votes.*.candidate_id' => ['required', 'integer', 'exists:candidates,id'],
            'detected_votes.*.confidence' => ['nullable', 'numeric'],
        ]);

        if (! empty($validated['election_id']) && ! $this->canScanElection((int) $validated['election_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'You are not assigned to scan ballots for the selected election.',
                'errors' => ['Election access denied.'],
            ], 403);
        }

        if (Ballot::query()->where('image_hash', $validated['image_hash'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Duplicate ballot detected. This scan was already submitted.',
                'errors' => ['Duplicate image hash.'],
            ], 409);
        }

        if (! empty($validated['election_id']) && ! empty($validated['ballot_number'])) {
            $ballotNumberExists = Ballot::query()
                ->where('election_id', $validated['election_id'])
                ->where('ballot_number', $validated['ballot_number'])
                ->exists();

            if ($ballotNumberExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'The ballot number already exists for this election.',
                    'errors' => ['Duplicate ballot number for election.'],
                ], 422);
            }
        }

        $candidateMap = Candidate::query()
            ->where('is_active', true)
            ->get(['id', 'position_id'])
            ->keyBy('id');

        $positionVoteLimits = Position::query()
            ->get(['id', 'votes_allowed'])
            ->mapWithKeys(fn ($position) => [$position->id => max(1, (int) ($position->votes_allowed ?? 1))]);

        $votesByPosition = [];
        foreach ($validated['detected_votes'] as $vote) {
            $positionId = (int) $vote['position_id'];
            $candidateId = (int) $vote['candidate_id'];
            $positionVotes = $votesByPosition[$positionId] ?? [];

            if (in_array($candidateId, $positionVotes, true)) {
                return response()->json([
                    'success' => false,
                    'message' => "Candidate {$candidateId} is duplicated in position {$positionId}.",
                    'errors' => ['Duplicate candidate in the same position.'],
                ], 422);
            }

            $candidate = $candidateMap->get($candidateId);
            if (! $candidate || (int) $candidate->position_id !== $positionId) {
                return response()->json([
                    'success' => false,
                    'message' => "Candidate {$candidateId} is invalid for position {$positionId}.",
                    'errors' => ['Candidate-position validation failed.'],
                ], 422);
            }

            $positionLimit = (int) ($positionVoteLimits->get($positionId) ?? 1);
            if (count($positionVotes) >= $positionLimit) {
                return response()->json([
                    'success' => false,
                    'message' => "Position {$positionId} allows only {$positionLimit} vote(s).",
                    'errors' => ['Position vote limit exceeded.'],
                ], 422);
            }

            $positionVotes[] = $candidateId;
            $votesByPosition[$positionId] = $positionVotes;
        }

        $ballot = DB::transaction(function () use ($validated, $votesByPosition) {
            $ballot = Ballot::query()->create([
                'election_id' => $validated['election_id'] ?? null,
                'ballot_number' => $validated['ballot_number'] ?? null,
                'uuid' => (string) Str::uuid(),
                'image_hash' => $validated['image_hash'],
                'scanned_at' => now(),
                'scanned_by' => auth()->id(),
                'status' => 'scanned',
            ]);

            foreach ($votesByPosition as $positionId => $candidateIds) {
                foreach ($candidateIds as $candidateId) {
                    Vote::query()->create([
                        'ballot_id' => $ballot->id,
                        'position_id' => $positionId,
                        'candidate_id' => $candidateId,
                        'is_valid' => true,
                    ]);
                }
            }

            return $ballot;
        });

        $savedVotes = Vote::query()
            ->with([
                'candidate:id,name,party',
                'position:id,name',
            ])
            ->where('ballot_id', $ballot->id)
            ->orderBy('position_id')
            ->orderBy('candidate_id')
            ->get();

        $submittedVotes = $savedVotes->map(function (Vote $vote) {
            return [
                'position_id' => (int) $vote->position_id,
                'position_name' => $vote->position?->name,
                'candidate_id' => (int) $vote->candidate_id,
                'candidate_name' => $vote->candidate?->name,
                'candidate_party' => $vote->candidate?->party,
                'confidence' => null,
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'message' => 'Ballot submitted successfully.',
            'ballot' => [
                'id' => $ballot->id,
                'uuid' => $ballot->uuid,
                'election_id' => $ballot->election_id,
                'ballot_number' => $ballot->ballot_number,
            ],
            'votes_saved' => count($submittedVotes),
            'submitted_votes' => $submittedVotes,
        ]);
    }

    private function buildBallotLayout($positions)
    {
        return $positions->flatMap(function ($position, int $rowIndex) {
            $positionVoteLimit = max(1, (int) ($position->votes_allowed ?? 1));
            return $position->candidates->values()->map(function ($candidate, int $colIndex) use ($position, $rowIndex, $positionVoteLimit) {
                return [
                    'row' => $rowIndex,
                    'col' => $colIndex,
                    'candidate_id' => $candidate->id,
                    'candidate_name' => $candidate->name,
                    'candidate_party' => $candidate->party,
                    'position_id' => $position->id,
                    'position_name' => $position->name,
                    'position_vote_limit' => $positionVoteLimit,
                ];
            });
        })->values();
    }

    private function canScanElection(int $electionId): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->isAdviser()) {
            return true;
        }

        return Election::query()
            ->where('id', $electionId)
            ->where(function ($query) use ($user) {
                $query->whereHas('facilitators', fn ($facilitatorQuery) => $facilitatorQuery->where('users.id', $user->id))
                    ->orWhere('facilitator_id', $user->id);
            })
            ->exists();
    }
}