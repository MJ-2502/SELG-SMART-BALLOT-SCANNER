<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_adviser_can_create_update_and_delete_a_user_account(): void
    {
        $this->actingAsAdviser();

        $createResponse = $this->post(route('users.store'), [
            'name' => 'Facilitator One',
            'email' => 'facilitator1@example.com',
            'role' => User::ROLE_FACILITATOR,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $createResponse->assertRedirect(route('users.index'));
        $createResponse->assertSessionHas('status', 'User account created successfully.');

        $user = User::query()->where('email', 'facilitator1@example.com')->firstOrFail();

        $updateResponse = $this->put(route('users.update', $user), [
            'name' => 'Facilitator Updated',
            'email' => 'facilitator-updated@example.com',
            'role' => User::ROLE_FACILITATOR,
            'password' => '',
            'password_confirmation' => '',
        ]);

        $updateResponse->assertRedirect(route('users.index'));
        $updateResponse->assertSessionHas('status', 'User account updated successfully.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Facilitator Updated',
            'email' => 'facilitator-updated@example.com',
            'role' => User::ROLE_FACILITATOR,
        ]);

        $deleteResponse = $this->delete(route('users.destroy', $user));

        $deleteResponse->assertRedirect(route('users.index'));
        $deleteResponse->assertSessionHas('status', 'User account deleted successfully.');
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_adviser_cannot_delete_their_own_account(): void
    {
        $adviser = $this->actingAsAdviser();

        $response = $this->delete(route('users.destroy', $adviser));

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('status', 'You cannot delete your own active account.');
        $this->assertDatabaseHas('users', ['id' => $adviser->id]);
    }

    public function test_facilitator_cannot_access_adviser_user_management_routes(): void
    {
        $facilitator = User::factory()->create([
            'role' => User::ROLE_FACILITATOR,
        ]);

        $response = $this->actingAs($facilitator)->get(route('users.index'));

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