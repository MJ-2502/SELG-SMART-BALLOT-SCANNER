<?php

namespace Tests\Feature;

use App\Models\Ballot;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BallotGenerationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_adviser_can_generate_ballots_for_election(): void
    {
        $this->actingAsAdviser();

        $election = Election::query()->create([
            'election_name' => 'Test Election A',
            'election_date' => now()->addDays(3),
            'status' => 'active',
        ]);

        $position = Position::query()->create([
            'name' => 'President',
            'display_order' => 1,
            'votes_allowed' => 1,
        ]);

        Candidate::query()->create([
            'position_id' => $position->id,
            'name' => 'Candidate A',
            'party' => 'Party One',
            'is_active' => true,
        ]);

        $response = $this->post(route('admin.ballot-generator.generate'), [
            'election_id' => $election->id,
            'print_count' => 3,
        ]);

        $response->assertRedirect(route('admin.ballot-generator.print', [
            'election' => $election->id,
            'per_sheet' => 2,
            'scale_percent' => 100,
        ], false));
        $response->assertSessionHas('status');

        $this->assertDatabaseCount('ballots', 3);
        $this->assertDatabaseHas('elections', [
            'id' => $election->id,
            'ballot_print_quantity' => 3,
        ]);
    }

    public function test_adviser_cannot_generate_ballots_without_partylist_entries(): void
    {
        $this->actingAsAdviser();

        $election = Election::query()->create([
            'election_name' => 'Test Election B',
            'election_date' => now()->addDays(3),
            'status' => 'active',
        ]);

        $position = Position::query()->create([
            'name' => 'President',
            'display_order' => 1,
            'votes_allowed' => 1,
        ]);

        Candidate::query()->create([
            'position_id' => $position->id,
            'name' => 'Candidate Without Partylist',
            'party' => null,
            'is_active' => true,
        ]);

        $response = $this->post(route('admin.ballot-generator.generate'), [
            'election_id' => $election->id,
            'print_count' => 3,
        ]);

        $response->assertRedirect(route('admin.ballot-generator.index', ['election' => $election->id], false));
        $response->assertSessionHasErrors([
            'target_election' => 'Before generating ballots for this election, add active partylist first in Candidate Management.',
        ]);

        $this->assertDatabaseCount('ballots', 0);
    }

    public function test_adviser_can_delete_pending_ballot_from_finished_election(): void
    {
        $this->actingAsAdviser();

        $election = Election::query()->create([
            'election_name' => 'Finished Election',
            'election_date' => now()->subDay(),
            'status' => 'completed',
        ]);

        $ballot = Ballot::query()->create([
            'election_id' => $election->id,
            'ballot_number' => 12,
            'uuid' => (string) Str::uuid(),
            'status' => 'pending',
        ]);

        $response = $this->delete(route('admin.ballot-management.destroy', $ballot));

        $response->assertRedirect(route('admin.ballot-management.index', ['election' => $election->id]));
        $response->assertSessionHas('status', 'Ballot #12 deleted successfully.');
        $this->assertDatabaseMissing('ballots', ['id' => $ballot->id]);
    }

    public function test_adviser_cannot_delete_non_pending_ballot_in_ballot_management(): void
    {
        $this->actingAsAdviser();

        $election = Election::query()->create([
            'election_name' => 'Completed Election',
            'election_date' => now()->subDay(),
            'status' => 'completed',
        ]);

        $ballot = Ballot::query()->create([
            'election_id' => $election->id,
            'ballot_number' => 20,
            'uuid' => (string) Str::uuid(),
            'status' => 'scanned',
        ]);

        $response = $this->delete(route('admin.ballot-management.destroy', $ballot));

        $response->assertRedirect(route('admin.ballot-management.index', ['election' => $election->id]));
        $response->assertSessionHas('error', 'Only pending generated ballots can be deleted.');
        $this->assertDatabaseHas('ballots', ['id' => $ballot->id]);
    }

    private function actingAsAdviser(): User
    {
        $adviser = User::factory()->create([
            'role' => User::ROLE_ADVISER,
        ]);

        $this->actingAs($adviser);

        return $adviser;
    }
}