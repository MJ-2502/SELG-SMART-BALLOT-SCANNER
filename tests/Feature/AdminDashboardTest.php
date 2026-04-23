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

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_adviser_sees_welcome_and_create_election_action(): void
    {
        $adviser = User::factory()->create([
            'role' => User::ROLE_ADVISER,
        ]);

        $response = $this->actingAs($adviser)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Welcome to SELG Ballot Scanner');
        $response->assertSee('Create Election');
    }

    public function test_adviser_with_election_sees_monitoring_cards(): void
    {
        $adviser = User::factory()->create([
            'role' => User::ROLE_ADVISER,
        ]);

        $election = Election::query()->create([
            'election_name' => 'Monitoring Election',
            'election_date' => now()->addDay(),
            'status' => 'active',
            'ballot_print_quantity' => 10,
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

        $validBallot = Ballot::query()->create([
            'election_id' => $election->id,
            'uuid' => (string) Str::uuid(),
            'status' => 'scanned',
            'scanned_by' => $adviser->id,
        ]);

        Vote::query()->create([
            'ballot_id' => $validBallot->id,
            'position_id' => $position->id,
            'candidate_id' => $candidate->id,
            'is_valid' => true,
        ]);

        $invalidBallot = Ballot::query()->create([
            'election_id' => $election->id,
            'uuid' => (string) Str::uuid(),
            'status' => 'scanned',
            'scanned_by' => $adviser->id,
        ]);

        Vote::query()->create([
            'ballot_id' => $invalidBallot->id,
            'position_id' => $position->id,
            'candidate_id' => $candidate->id,
            'is_valid' => false,
        ]);

        $response = $this->actingAs($adviser)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Dashboard');
        $response->assertSee('Ballots Scanned');
        $response->assertSee('Valid Ballots');
        $response->assertSee('Invalid Ballots');
        $response->assertSee('Voter Turnout');
    }
}
