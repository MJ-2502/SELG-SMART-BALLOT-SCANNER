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
use Illuminate\View\View;

class ScannerController extends Controller
{
    public function index(): View
    {
        $elections = Election::query()
            ->orderByDesc('election_date')
            ->get();

        $positions = Position::query()
            ->with(['candidates' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
            ->whereHas('candidates', fn ($query) => $query->where('is_active', true))
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return view('scanner.index', [
            'elections' => $elections,
            'positions' => $positions,
            'serviceUrl' => config('omr.service_url'),
            'layoutCount' => $this->buildBallotLayout($positions)->count(),
        ]);
    }

    public function scan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ballot_image' => ['required', 'file', 'image', 'max:10240'],
            'election_id' => ['nullable', 'integer', 'exists:elections,id'],
            'ballot_number' => ['nullable', 'string', 'max:255'],
        ]);

        $positions = Position::query()
            ->with(['candidates' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
            ->whereHas('candidates', fn ($query) => $query->where('is_active', true))
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

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

        try {
            $response = Http::acceptJson()
                ->timeout($timeout)
                ->post($serviceUrl . '/scan', $payload);
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
            ->get(['id', 'position_id'])
            ->keyBy('id');

        $warnings = [];
        $validVotes = [];
        $seenPositions = [];

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

            if (isset($seenPositions[$positionId])) {
                $warnings[] = "Multiple selections detected for position {$positionId}; keeping first result.";
                continue;
            }

            $seenPositions[$positionId] = true;
            $validVotes[] = [
                'position_id' => $positionId,
                'candidate_id' => $candidateId,
                'confidence' => (float) ($vote['confidence'] ?? 0),
                'candidate_name' => $vote['candidate_name'] ?? null,
                'row' => $vote['row'] ?? null,
                'col' => $vote['col'] ?? null,
            ];
        }

        $responseData['scan_preview'] = [
            'can_submit' => count($validVotes) > 0,
            'image_hash' => $imageHash,
            'detected_votes' => $validVotes,
            'warnings' => $warnings,
        ];

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

        $votesByPosition = [];
        foreach ($validated['detected_votes'] as $vote) {
            $positionId = (int) $vote['position_id'];
            $candidateId = (int) $vote['candidate_id'];

            if (isset($votesByPosition[$positionId])) {
                return response()->json([
                    'success' => false,
                    'message' => "Multiple votes for position {$positionId} are not allowed.",
                    'errors' => ['One vote per position rule violated.'],
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

            $votesByPosition[$positionId] = $candidateId;
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

            foreach ($votesByPosition as $positionId => $candidateId) {
                Vote::query()->create([
                    'ballot_id' => $ballot->id,
                    'position_id' => $positionId,
                    'candidate_id' => $candidateId,
                    'is_valid' => true,
                ]);
            }

            return $ballot;
        });

        return response()->json([
            'success' => true,
            'message' => 'Ballot submitted successfully.',
            'ballot' => [
                'id' => $ballot->id,
                'uuid' => $ballot->uuid,
                'election_id' => $ballot->election_id,
                'ballot_number' => $ballot->ballot_number,
            ],
            'votes_saved' => count($votesByPosition),
        ]);
    }

    private function buildBallotLayout($positions)
    {
        return $positions->flatMap(function ($position, int $rowIndex) {
            return $position->candidates->values()->map(function ($candidate, int $colIndex) use ($position, $rowIndex) {
                return [
                    'row' => $rowIndex,
                    'col' => $colIndex,
                    'candidate_id' => $candidate->id,
                    'candidate_name' => $candidate->name,
                    'position_id' => $position->id,
                ];
            });
        })->values();
    }
}