<?php

namespace Database\Seeders;

use App\Models\Candidate;
use App\Models\Position;
use Illuminate\Database\Seeder;

class CandidateSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'President' => [
                ['name' => 'Alex Reyes', 'party' => 'Unity'],
                ['name' => 'Morgan Lee', 'party' => 'Forward'],
            ],
            'Vice President' => [
                ['name' => 'Sam Cruz', 'party' => 'Unity'],
                ['name' => 'Jordan Lim', 'party' => 'Forward'],
            ],
            'Secretary' => [
                ['name' => 'Taylor Ong', 'party' => 'Unity'],
                ['name' => 'Casey Tan', 'party' => 'Forward'],
            ],
            'Treasurer' => [
                ['name' => 'Jamie Yu', 'party' => 'Unity'],
                ['name' => 'Riley Chua', 'party' => 'Forward'],
            ],
        ];

        foreach ($data as $positionName => $candidates) {
            $position = Position::where('name', $positionName)->first();

            if (! $position) {
                continue;
            }

            foreach ($candidates as $candidate) {
                Candidate::updateOrCreate(
                    [
                        'position_id' => $position->id,
                        'name' => $candidate['name'],
                    ],
                    [
                        'party' => $candidate['party'],
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
