<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_adviser_can_create_update_and_delete_a_facilitator_account(): void
    {
        $this->actingAsAdviser();

        $createResponse = $this->post(route('facilitators.store'), [
            'name' => 'Facilitator One',
            'username' => 'facilitator1',
            'grade_level' => '12',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $createResponse->assertRedirect(route('facilitators.index'));
        $createResponse->assertSessionHas('status', 'Facilitator account created successfully.');

        $user = User::query()->where('username', 'facilitator1')->firstOrFail();

        $updateResponse = $this->put(route('facilitators.update', $user), [
            'name' => 'Facilitator Updated',
            'username' => 'facilitator-updated',
            'grade_level' => '11',
            'password' => '',
            'password_confirmation' => '',
        ]);

        $updateResponse->assertRedirect(route('facilitators.index'));
        $updateResponse->assertSessionHas('status', 'Facilitator account updated successfully.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Facilitator Updated',
            'username' => 'facilitator-updated',
            'grade_level' => '11',
            'role' => User::ROLE_FACILITATOR,
        ]);

        $deleteResponse = $this->delete(route('facilitators.destroy', $user));

        $deleteResponse->assertRedirect(route('facilitators.index'));
        $deleteResponse->assertSessionHas('status', 'Facilitator account deleted successfully.');
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_adviser_cannot_delete_their_own_account_via_facilitator_routes(): void
    {
        $adviser = $this->actingAsAdviser();

        $response = $this->delete(route('facilitators.destroy', $adviser));

        $response->assertNotFound();
        $this->assertDatabaseHas('users', ['id' => $adviser->id]);
    }

    public function test_facilitator_cannot_access_adviser_facilitator_management_routes(): void
    {
        $facilitator = User::factory()->create([
            'role' => User::ROLE_FACILITATOR,
        ]);

        $response = $this->actingAs($facilitator)->get(route('facilitators.index'));

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