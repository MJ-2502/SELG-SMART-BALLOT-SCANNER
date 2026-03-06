<?php

namespace Database\Seeders;

use App\Models\Position;
use Illuminate\Database\Seeder;

class PositionSeeder extends Seeder
{
    public function run(): void
    {
        $positions = [
            ['name' => 'President', 'display_order' => 1],
            ['name' => 'Vice President', 'display_order' => 2],
            ['name' => 'Secretary', 'display_order' => 3],
            ['name' => 'Treasurer', 'display_order' => 4],
        ];

        foreach ($positions as $position) {
            Position::updateOrCreate(['name' => $position['name']], $position);
        }
    }
}
