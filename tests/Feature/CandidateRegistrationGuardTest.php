<?php

namespace Tests\Feature;

use App\Models\Election;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CandidateRegistrationGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_adviser_cannot_open_candidate_create_when_no_election_exists(): void
    {
        $this->actingAsAdviser();

        $response = $this->get(route('candidates.create'));

        $response->assertRedirect(route('candidates.index'));
        $response->assertSessionHas('error', 'Create an election first before adding candidates or creating a partylist.');
    }

    public function test_adviser_cannot_open_partylist_create_when_no_election_exists(): void
    {
        $this->actingAsAdviser();

        $response = $this->get(route('candidates.partylist.create'));

        $response->assertRedirect(route('candidates.index'));
        $response->assertSessionHas('error', 'Create an election first before adding candidates or creating a partylist.');
    }

    public function test_adviser_cannot_store_candidate_when_no_election_exists(): void
    {
        $this->actingAsAdviser();
        $position = Position::query()->create([
            'name' => 'President',
            'display_order' => 1,
        ]);

        $response = $this->post(route('candidates.store'), [
            'position_id' => $position->id,
            'name' => 'Jane Doe',
            'party' => 'Unity',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('candidates.index'));
        $response->assertSessionHas('error', 'Create an election first before adding candidates or creating a partylist.');
        $this->assertDatabaseCount('candidates', 0);
    }

    public function test_adviser_can_open_candidate_create_when_election_exists(): void
    {
        Election::query()->create([
            'election_name' => 'SELG General Election',
            'election_date' => now(),
        ]);

        $this->actingAsAdviser();

        $response = $this->get(route('candidates.create'));

        $response->assertOk();
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
