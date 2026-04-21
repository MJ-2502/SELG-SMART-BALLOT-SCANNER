<?php

namespace Tests\Feature;

use App\Models\Ballot;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Position;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ElectionProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_adviser_can_view_election_progress_metrics(): void
    {
        $adviser = User::factory()->create(['role' => User::ROLE_ADVISER]);

        $election = Election::query()->create([
            'election_name' => 'Progress Election',
            'election_date' => now()->addDay(),
            'status' => 'active',
        ]);

        $position = Position::query()->create([
            'name' => 'President',
            'display_order' => 1,
            'votes_allowed' => 1,
        ]);

        $candidateA = Candidate::query()->create([
            'position_id' => $position->id,
            'name' => 'Candidate A',
            'party' => 'Party A',
            'is_active' => true,
        ]);

        $candidateB = Candidate::query()->create([
            'position_id' => $position->id,
            'name' => 'Candidate B',
            'party' => 'Party B',
            'is_active' => true,
        ]);

        $validBallot = Ballot::query()->create([
            'election_id' => $election->id,
            'uuid' => (string) Str::uuid(),
            'status' => 'scanned',
            'scanned_at' => now()->subHour(),
            'scanned_by' => $adviser->id,
        ]);

        Vote::query()->create([
            'ballot_id' => $validBallot->id,
            'position_id' => $position->id,
            'candidate_id' => $candidateA->id,
            'is_valid' => true,
        ]);

        $flaggedBallot = Ballot::query()->create([
            'election_id' => $election->id,
            'uuid' => (string) Str::uuid(),
            'status' => 'scanned',
            'scanned_at' => now()->subMinutes(30),
            'scanned_by' => $adviser->id,
        ]);

        Vote::query()->create([
            'ballot_id' => $flaggedBallot->id,
            'position_id' => $position->id,
            'candidate_id' => $candidateB->id,
            'is_valid' => false,
        ]);

        Ballot::query()->create([
            'election_id' => $election->id,
            'uuid' => (string) Str::uuid(),
            'status' => 'pending',
            'scanned_by' => $adviser->id,
        ]);

        $response = $this->actingAs($adviser)->get(route('admin.progress', [
            'election' => $election->id,
        ]));

        $response->assertOk();
        $response->assertViewHas('metrics', function (array $metrics): bool {
            return $metrics['total_scanned'] === 2
                && $metrics['valid_submissions'] === 1
                && $metrics['flagged_submissions'] === 1;
        });
        $response->assertSee('Election Progress Monitoring');
    }

    public function test_facilitator_cannot_access_election_progress_page(): void
    {
        $facilitator = User::factory()->create(['role' => User::ROLE_FACILITATOR]);

        $response = $this->actingAs($facilitator)->get(route('admin.progress'));

        $response->assertForbidden();
    }
}
