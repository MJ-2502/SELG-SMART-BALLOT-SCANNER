<?php

namespace Tests\Feature;

use App\Models\Ballot;
use App\Models\Election;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ElectionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_adviser_can_create_start_and_stop_election(): void
    {
        $this->actingAsAdviser();

        $createResponse = $this->post(route('elections.store'), [
            'election_name' => 'SELG Election 2026',
            'election_date' => now()->addDays(2)->toDateTimeString(),
        ]);

        $createResponse->assertRedirect(route('elections.index'));
        $createResponse->assertSessionHas('status', 'Election created successfully.');

        $election = Election::query()->firstOrFail();
        $this->assertSame('pending', $election->status);

        $startResponse = $this->post(route('elections.start', $election));
        $startResponse->assertRedirect(route('elections.index'));
        $startResponse->assertSessionHas('status');

        $this->assertSame('active', $election->fresh()->status);

        $stopResponse = $this->post(route('elections.stop', $election));
        $stopResponse->assertRedirect(route('elections.index'));
        $stopResponse->assertSessionHas('status');

        $this->assertSame('completed', $election->fresh()->status);
    }

    public function test_adviser_can_delete_non_active_election_and_related_ballots(): void
    {
        $this->actingAsAdviser();

        $election = Election::query()->create([
            'election_name' => 'Completed Election',
            'election_date' => now()->subDays(1),
            'status' => 'completed',
            'ballot_print_quantity' => 2,
        ]);

        Ballot::query()->create([
            'election_id' => $election->id,
            'ballot_number' => 1,
            'uuid' => (string) Str::uuid(),
            'status' => 'pending',
        ]);

        Ballot::query()->create([
            'election_id' => $election->id,
            'ballot_number' => 2,
            'uuid' => (string) Str::uuid(),
            'status' => 'pending',
        ]);

        $response = $this->delete(route('elections.destroy', $election));

        $response->assertRedirect(route('elections.index'));
        $response->assertSessionHas('status');
        $this->assertDatabaseMissing('elections', ['id' => $election->id]);
        $this->assertDatabaseMissing('ballots', ['election_id' => $election->id]);
    }

    public function test_facilitator_cannot_access_adviser_election_routes(): void
    {
        $facilitator = User::factory()->create([
            'role' => User::ROLE_FACILITATOR,
        ]);

        $response = $this->actingAs($facilitator)->get(route('elections.index'));

        $response->assertForbidden();
    }

    public function test_adviser_can_assign_multiple_facilitators_to_election(): void
    {
        $this->actingAsAdviser();

        $election = Election::query()->create([
            'election_name' => 'SELG Election 2026',
            'election_date' => now()->addDays(2),
            'status' => 'pending',
        ]);

        $facilitatorA = User::factory()->create([
            'role' => User::ROLE_FACILITATOR,
        ]);
        $facilitatorB = User::factory()->create([
            'role' => User::ROLE_FACILITATOR,
        ]);

        $response = $this->patch(route('elections.facilitators.assign', $election), [
            'facilitator_ids' => [$facilitatorA->id, $facilitatorB->id],
        ]);

        $response->assertRedirect(route('elections.index'));
        $response->assertSessionHas('status', 'Election facilitator assignments updated successfully.');

        $this->assertDatabaseHas('election_facilitator', [
            'election_id' => $election->id,
            'facilitator_id' => $facilitatorA->id,
        ]);
        $this->assertDatabaseHas('election_facilitator', [
            'election_id' => $election->id,
            'facilitator_id' => $facilitatorB->id,
        ]);
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