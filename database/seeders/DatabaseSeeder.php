<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdviserSeeder::class,
            PositionSeeder::class,
            CandidateSeeder::class,
            ElectionSeeder::class,
            VoteVisualizationSeeder::class,
        ]);
    }
}
