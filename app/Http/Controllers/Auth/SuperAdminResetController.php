<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class SuperAdminResetController extends Controller
{
    public function show(Request $request): Response
    {
        $token = (string) $request->query('token', '');

        $this->ensureValidToken($token);

        return Inertia::render('Auth/SuperAdminReset', [
            'token' => $token,
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $this->ensureValidToken($validated['token']);

        $adviser = User::query()
            ->where('role', User::ROLE_ADVISER)
            ->orderBy('id')
            ->firstOrFail();

        $adviser->forceFill([
            'password' => Hash::make($validated['password']),
            'remember_token' => null,
        ])->save();

        return redirect()
            ->route('login')
            ->with('status', 'Adviser password has been reset successfully.');
    }

    private function ensureValidToken(?string $providedToken): void
    {
        $configuredToken = (string) config('app.superadmin_reset_token');

        if ($configuredToken === '' || $providedToken === null || ! hash_equals($configuredToken, $providedToken)) {
            abort(403);
        }
    }
}
