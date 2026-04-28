<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_forgot_password_routes_are_not_available(): void
    {
        $this->get('/forgot-password')->assertNotFound();
        $this->post('/forgot-password', ['email' => 'adviser@example.test'])->assertNotFound();
    }

    public function test_public_reset_password_routes_are_not_available(): void
    {
        $this->get('/reset-password/fake-token')->assertNotFound();
        $this->post('/reset-password', [
            'token' => 'fake-token',
            'email' => 'adviser@example.test',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertNotFound();
    }

    public function test_superadmin_reset_page_requires_valid_token(): void
    {
        config()->set('app.superadmin_reset_token', 'valid-secret-token');

        $this->get('/admin/superadmin')->assertForbidden();
        $this->get('/admin/superadmin?token=invalid-secret-token')->assertForbidden();

        $response = $this->get('/admin/superadmin?token=valid-secret-token');

        $response->assertOk();
    }

    public function test_superadmin_reset_updates_adviser_password_with_valid_token(): void
    {
        config()->set('app.superadmin_reset_token', 'valid-secret-token');

        $adviser = User::factory()->create([
            'role' => User::ROLE_ADVISER,
            'password' => Hash::make('old-password'),
        ]);

        $response = $this->post('/admin/superadmin', [
            'token' => 'valid-secret-token',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status', 'Adviser password has been reset successfully.');

        $adviser->refresh();
        $this->assertTrue(Hash::check('new-secure-password', $adviser->password));
    }

    public function test_superadmin_reset_rejects_invalid_submit_token(): void
    {
        config()->set('app.superadmin_reset_token', 'valid-secret-token');

        User::factory()->create([
            'role' => User::ROLE_ADVISER,
        ]);

        $response = $this->post('/admin/superadmin', [
            'token' => 'wrong-token',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ]);

        $response->assertForbidden();
    }
}
