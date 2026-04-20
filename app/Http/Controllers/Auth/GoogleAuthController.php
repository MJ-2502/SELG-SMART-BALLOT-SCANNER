<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        $googleUser = Socialite::driver('google')->user();

        $user = User::query()
            ->where('google_id', $googleUser->id)
            ->orWhere('email', $googleUser->email)
            ->first();

        if ($user) {
            if (! $user->google_id) {
                $user->update(['google_id' => $googleUser->id]);
            }
        } else {
            $user = User::create([
                'name' => $googleUser->name ?: 'Google User',
                'username' => $this->buildUniqueUsername($googleUser->nickname ?: $googleUser->email ?: 'googleuser'),
                'email' => $googleUser->email ?: $this->buildFallbackEmail($googleUser->id),
                'google_id' => $googleUser->id,
                'password' => Hash::make(Str::random(40)),
                'email_verified_at' => now(),
            ]);
        }

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function buildUniqueUsername(string $seed): string
    {
        $normalized = Str::lower(trim($seed));
        $normalized = Str::before($normalized, '@');
        $base = Str::of($normalized)
            ->replaceMatches('/[^a-z0-9_\-]/', '')
            ->trim('_-')
            ->value();

        if ($base === '') {
            $base = 'user';
        }

        $candidate = $base;
        $counter = 1;

        while (User::query()->where('username', $candidate)->exists()) {
            $counter++;
            $candidate = $base.$counter;
        }

        return $candidate;
    }

    private function buildFallbackEmail(string $googleId): string
    {
        return sprintf('google_%s@local.sselg', $googleId);
    }
}
