<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdviserSeeder extends Seeder
{
    public function run(): void
    {
        $username = strtolower((string) env('ADVISER_USERNAME', 'adviser'));
        $defaultPassword = (string) env('ADVISER_DEFAULT_PASSWORD', 'ChangeMeImmediately!2026');

        $adviser = User::query()->firstOrCreate(
            ['username' => $username],
            [
                'name' => 'Election Adviser',
                'email' => $username.'@local.sselg',
                'password' => Hash::make($defaultPassword),
                'role' => User::ROLE_ADVISER,
            ]
        );

        if (! $adviser->isAdviser()) {
            $adviser->role = User::ROLE_ADVISER;
            $adviser->save();
        }

        User::query()
            ->where('role', User::ROLE_ADVISER)
            ->whereKeyNot($adviser->id)
            ->update(['role' => User::ROLE_FACILITATOR]);
    }
}
