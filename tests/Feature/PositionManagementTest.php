<?php

namespace Tests\Feature;

use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PositionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_adviser_can_create_update_and_delete_position(): void
    {
        $this->actingAsAdviser();

        $createResponse = $this->post(route('positions.store'), [
            'name' => 'President',
            'display_order' => 1,
            'votes_allowed' => 1,
        ]);

        $createResponse->assertRedirect(route('positions.index'));
        $createResponse->assertSessionHas('status', 'Position created successfully.');

        $position = Position::query()->where('name', 'President')->firstOrFail();

        $updateResponse = $this->put(route('positions.update', $position), [
            'name' => 'Vice President',
            'display_order' => 2,
            'votes_allowed' => 2,
        ]);

        $updateResponse->assertRedirect(route('positions.index'));
        $updateResponse->assertSessionHas('status', 'Position updated successfully.');

        $this->assertDatabaseHas('positions', [
            'id' => $position->id,
            'name' => 'Vice President',
            'display_order' => 2,
            'votes_allowed' => 2,
        ]);

        $deleteResponse = $this->delete(route('positions.destroy', $position));

        $deleteResponse->assertRedirect(route('positions.index'));
        $deleteResponse->assertSessionHas('status', 'Position deleted successfully.');
        $this->assertDatabaseMissing('positions', ['id' => $position->id]);
    }

    public function test_facilitator_cannot_access_adviser_position_routes(): void
    {
        $facilitator = User::factory()->create([
            'role' => User::ROLE_FACILITATOR,
        ]);

        $response = $this->actingAs($facilitator)->get(route('positions.index'));

        $response->assertForbidden();
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