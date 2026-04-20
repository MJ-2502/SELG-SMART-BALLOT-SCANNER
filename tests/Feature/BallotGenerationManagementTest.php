<?php

namespace Tests\Feature;

use App\Models\Ballot;
use App\Models\Election;
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
            'election_date' => now()->addDays(3),
            'status' => 'active',
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

    public function test_adviser_can_delete_pending_ballot_from_finished_election(): void
    {
        $this->actingAsAdviser();

        $election = Election::query()->create([
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