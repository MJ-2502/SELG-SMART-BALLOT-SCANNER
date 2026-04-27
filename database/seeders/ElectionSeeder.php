<?php

namespace Database\Seeders;

use App\Models\Election;
use Illuminate\Database\Seeder;

class ElectionSeeder extends Seeder
{
    public function run(): void
    {
        $electionName = 'Default SELG Election';

        Election::firstOrCreate(
            ['election_name' => $electionName],
            [
                'election_date' => now()->addWeek()->setTime(8, 0),
                'ballot_print_quantity' => 0,
                'status' => 'pending',
            ]
        );
    }
}
