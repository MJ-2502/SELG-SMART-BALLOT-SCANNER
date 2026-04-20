<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CandidatePartylistDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_adviser_can_delete_a_partylist_and_all_its_candidates(): void
    {
        $this->actingAsAdviser();
        $position = Position::query()->create([
            'name' => 'President',
            'display_order' => 1,
        ]);

        Candidate::query()->create([
            'position_id' => $position->id,
            'name' => 'Alpha One',
            'party' => 'Alpha',
            'is_active' => true,
        ]);
        Candidate::query()->create([
            'position_id' => $position->id,
            'name' => 'Alpha Two',
            'party' => 'Alpha',
            'is_active' => true,
        ]);
        Candidate::query()->create([
            'position_id' => $position->id,
            'name' => 'Beta One',
            'party' => 'Beta',
            'is_active' => true,
        ]);

        $response = $this->delete(route('candidates.partylist.destroy'), [
            'party' => 'Alpha',
        ]);

        $response->assertRedirect(route('candidates.index'));
        $response->assertSessionHas('status', 'Partylist "Alpha" deleted. Removed 2 candidate(s).');

        $this->assertDatabaseMissing('candidates', ['name' => 'Alpha One', 'party' => 'Alpha']);
        $this->assertDatabaseMissing('candidates', ['name' => 'Alpha Two', 'party' => 'Alpha']);
        $this->assertDatabaseHas('candidates', ['name' => 'Beta One', 'party' => 'Beta']);
    }

    public function test_adviser_gets_error_when_deleting_non_existent_partylist(): void
    {
        $this->actingAsAdviser();

        $response = $this->delete(route('candidates.partylist.destroy'), [
            'party' => 'Unknown Party',
        ]);

        $response->assertRedirect(route('candidates.index'));
        $response->assertSessionHas('error', 'Partylist not found or already deleted.');
    }

    public function test_adviser_must_provide_party_name_when_deleting_partylist(): void
    {
        $this->actingAsAdviser();

        $response = $this->from(route('candidates.index'))->delete(route('candidates.partylist.destroy'), [
            'party' => '',
        ]);

        $response->assertRedirect(route('candidates.index'));
        $response->assertSessionHasErrors('party');
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
