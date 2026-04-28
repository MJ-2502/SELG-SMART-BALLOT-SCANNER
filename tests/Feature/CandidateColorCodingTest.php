<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Election;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CandidateColorCodingTest extends TestCase
{
    use RefreshDatabase;

    public function test_independent_candidate_color_cannot_be_reused_by_another_independent_candidate(): void
    {
        $this->actingAsAdviser();
        $this->createElection();
        $position = $this->createPosition('President', 1);

        Candidate::query()->create([
            'position_id' => $position->id,
            'name' => 'Existing Independent',
            'party' => null,
            'color_code' => '#EF4444',
            'is_active' => true,
        ]);

        $response = $this->from(route('candidates.create'))->post(route('candidates.store'), [
            'position_id' => $position->id,
            'name' => 'New Independent',
            'party' => null,
            'color_code' => '#EF4444',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('candidates.create'));
        $response->assertSessionHasErrors('color_code');
        $this->assertDatabaseMissing('candidates', ['name' => 'New Independent']);
    }

    public function test_same_partylist_can_reuse_its_existing_color_code(): void
    {
        $this->actingAsAdviser();
        $this->createElection();
        $president = $this->createPosition('President', 1);
        $vicePresident = $this->createPosition('Vice President', 2);

        Candidate::query()->create([
            'position_id' => $president->id,
            'name' => 'Alpha One',
            'party' => 'Alpha',
            'color_code' => '#0EA5E9',
            'is_active' => true,
        ]);

        $response = $this->post(route('candidates.store'), [
            'position_id' => $vicePresident->id,
            'name' => 'Alpha Two',
            'party' => 'Alpha',
            'color_code' => '#0EA5E9',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('candidates.index'));
        $this->assertDatabaseHas('candidates', [
            'name' => 'Alpha Two',
            'party' => 'Alpha',
            'color_code' => '#0EA5E9',
        ]);
    }

    public function test_partylist_cannot_use_color_code_that_is_already_assigned_to_other_partylist(): void
    {
        $this->actingAsAdviser();
        $this->createElection();
        $position = $this->createPosition('Secretary', 1);

        Candidate::query()->create([
            'position_id' => $position->id,
            'name' => 'Beta One',
            'party' => 'Beta',
            'color_code' => '#22C55E',
            'is_active' => true,
        ]);

        $response = $this->from(route('candidates.partylist.create'))->post(route('candidates.partylist.store'), [
            'party' => 'Gamma',
            'color_code' => '#22C55E',
            'is_active' => 1,
            'entries' => [
                $position->id => 'Gamma One',
            ],
        ]);

        $response->assertRedirect(route('candidates.partylist.create'));
        $response->assertSessionHasErrors('color_code');
        $this->assertDatabaseMissing('candidates', ['name' => 'Gamma One', 'party' => 'Gamma']);
    }

    public function test_partylist_cannot_use_different_color_from_its_existing_color_code(): void
    {
        $this->actingAsAdviser();
        $this->createElection();
        $position = $this->createPosition('Treasurer', 1);

        Candidate::query()->create([
            'position_id' => $position->id,
            'name' => 'Alpha One',
            'party' => 'Alpha',
            'color_code' => '#3B82F6',
            'is_active' => true,
        ]);

        $response = $this->from(route('candidates.create'))->post(route('candidates.store'), [
            'position_id' => $position->id,
            'name' => 'Alpha Two',
            'party' => 'Alpha',
            'color_code' => '#F97316',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('candidates.create'));
        $response->assertSessionHasErrors('color_code');
        $this->assertDatabaseMissing('candidates', ['name' => 'Alpha Two', 'party' => 'Alpha']);
    }

    private function actingAsAdviser(): User
    {
        $adviser = User::factory()->create([
            'role' => User::ROLE_ADVISER,
        ]);

        $this->actingAs($adviser);

        return $adviser;
    }

    private function createElection(): Election
    {
        return Election::query()->create([
            'election_name' => 'SELG General Election',
            'election_date' => now(),
        ]);
    }

    private function createPosition(string $name, int $order): Position
    {
        return Position::query()->create([
            'name' => $name,
            'display_order' => $order,
        ]);
    }
}
