<?php

namespace Tests\Feature;

use App\Models\Ballot;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScannerWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_open_scanner_page(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_FACILITATOR,
        ]);

        $response = $this->actingAs($user)->get(route('scanner.index'));

        $response->assertOk();
    }

    public function test_scan_returns_error_when_no_active_candidates_are_configured(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_FACILITATOR,
        ]);

        $image = $this->minimalPngUpload();

        $response = $this->actingAs($user)->post(route('scanner.scan'), [
            'ballot_image' => $image,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'No active candidates are configured yet.',
        ]);
    }

    private function minimalPngUpload(): UploadedFile
    {
        // A valid 1x1 PNG file (base64-encoded) to avoid requiring GD in test environments.
        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO2x6wAAAABJRU5ErkJggg==');

        $tempPath = tempnam(sys_get_temp_dir(), 'scan_');
        file_put_contents($tempPath, $pngBytes);

        return new UploadedFile(
            $tempPath,
            'ballot.png',
            'image/png',
            null,
            true
        );
    }

    public function test_submit_saves_ballot_and_votes(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_FACILITATOR,
        ]);

        $election = Election::query()->create([
            'election_date' => now()->addDay(),
            'status' => 'active',
        ]);

        $position = Position::query()->create([
            'name' => 'President',
            'display_order' => 1,
            'votes_allowed' => 1,
        ]);

        $candidate = Candidate::query()->create([
            'position_id' => $position->id,
            'name' => 'Candidate One',
            'party' => 'Party A',
            'is_active' => true,
        ]);

        $imageHash = str_repeat('a', 64);

        $response = $this->actingAs($user)->postJson(route('scanner.submit'), [
            'image_hash' => $imageHash,
            'election_id' => $election->id,
            'ballot_number' => 101,
            'detected_votes' => [
                [
                    'position_id' => $position->id,
                    'candidate_id' => $candidate->id,
                    'confidence' => 0.88,
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Ballot submitted successfully.',
            'votes_saved' => 1,
        ]);

        $this->assertDatabaseHas('ballots', [
            'image_hash' => $imageHash,
            'election_id' => $election->id,
            'ballot_number' => 101,
            'scanned_by' => $user->id,
            'status' => 'scanned',
        ]);

        $ballot = Ballot::query()->where('image_hash', $imageHash)->firstOrFail();

        $this->assertDatabaseHas('votes', [
            'ballot_id' => $ballot->id,
            'position_id' => $position->id,
            'candidate_id' => $candidate->id,
            'is_valid' => 1,
        ]);
    }

    public function test_submit_rejects_duplicate_image_hash(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_FACILITATOR,
        ]);

        $position = Position::query()->create([
            'name' => 'Secretary',
            'display_order' => 2,
            'votes_allowed' => 1,
        ]);

        $candidate = Candidate::query()->create([
            'position_id' => $position->id,
            'name' => 'Candidate Two',
            'party' => 'Party B',
            'is_active' => true,
        ]);

        $hash = str_repeat('b', 64);

        Ballot::query()->create([
            'uuid' => (string) Str::uuid(),
            'image_hash' => $hash,
            'status' => 'scanned',
            'scanned_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->postJson(route('scanner.submit'), [
            'image_hash' => $hash,
            'detected_votes' => [
                [
                    'position_id' => $position->id,
                    'candidate_id' => $candidate->id,
                    'confidence' => 0.91,
                ],
            ],
        ]);

        $response->assertStatus(409);
        $response->assertJson([
            'success' => false,
            'message' => 'Duplicate ballot detected. This scan was already submitted.',
        ]);
    }

    public function test_submit_rejects_votes_exceeding_position_limit(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_FACILITATOR,
        ]);

        $position = Position::query()->create([
            'name' => 'Treasurer',
            'display_order' => 3,
            'votes_allowed' => 1,
        ]);

        $candidateA = Candidate::query()->create([
            'position_id' => $position->id,
            'name' => 'Candidate A',
            'party' => 'Party C',
            'is_active' => true,
        ]);

        $candidateB = Candidate::query()->create([
            'position_id' => $position->id,
            'name' => 'Candidate B',
            'party' => 'Party C',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->postJson(route('scanner.submit'), [
            'image_hash' => str_repeat('c', 64),
            'detected_votes' => [
                [
                    'position_id' => $position->id,
                    'candidate_id' => $candidateA->id,
                    'confidence' => 0.9,
                ],
                [
                    'position_id' => $position->id,
                    'candidate_id' => $candidateB->id,
                    'confidence' => 0.85,
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => "Position {$position->id} allows only 1 vote(s).",
        ]);
    }
}