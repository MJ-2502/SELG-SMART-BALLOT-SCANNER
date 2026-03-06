<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'adviser@selg.local'],
            [
                'name' => 'Election Adviser',
                'password' => Hash::make('password'),
                'role' => User::ROLE_ADVISER,
            ]
        );

        User::updateOrCreate(
            ['email' => 'facilitator@selg.local'],
            [
                'name' => 'Election Facilitator',
                'password' => Hash::make('password'),
                'role' => User::ROLE_FACILITATOR,
            ]
        );
    }
}
