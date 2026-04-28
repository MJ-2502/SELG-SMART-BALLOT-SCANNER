<?php

namespace Database\Seeders;

use App\Models\Candidate;
use App\Models\Ballot;
use App\Models\Election;
use App\Models\Position;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VoteVisualizationSeeder extends Seeder
{
    public function run(): void
    {
        $election = Election::query()
            ->where('status', 'active')
            ->orderByDesc('election_date')
            ->first()
            ?? Election::query()->orderByDesc('election_date')->first();

        if (! $election) {
            $this->command?->warn('VoteVisualizationSeeder: No election found. Run ElectionSeeder first.');

            return;
        }

        $scannerId = User::query()->where('role', User::ROLE_FACILITATOR)->value('id')
            ?? User::query()->where('role', User::ROLE_ADVISER)->value('id');

        $positions = Position::query()
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        if ($positions->isEmpty()) {
            $this->command?->warn('VoteVisualizationSeeder: No positions found. Run PositionSeeder first.');

            return;
        }

        $totalBallots = 1500;
        $seedPrefix = sprintf('seed-viz-%d-', $election->id);

        DB::transaction(function () use ($election, $positions, $scannerId, $totalBallots, $seedPrefix): void {
            Vote::query()->delete();
            Ballot::query()->delete();
            Candidate::query()->delete();

            $candidateRoster = $this->seedCrowdedCandidates($positions);

            $nextBallotNumber = 1;

            for ($offset = 0; $offset < $totalBallots; $offset++) {
                $ballotNumber = $nextBallotNumber + $offset;
                $flaggedChance = random_int(1, 100);
                $isFlagged = $flaggedChance <= 10;
                $shouldCreateVotes = ! $isFlagged || random_int(1, 100) <= 88;

                $ballot = Ballot::query()->create([
                    'election_id' => $election->id,
                    'ballot_number' => $ballotNumber,
                    'uuid' => (string) Str::uuid(),
                    'image_hash' => $seedPrefix.$ballotNumber,
                    'status' => 'scanned',
                    'scanned_at' => now()->subMinutes(random_int(1, 900)),
                    'scanned_by' => $scannerId,
                ]);

                if (! $shouldCreateVotes) {
                    continue;
                }

                foreach ($positions as $position) {
                    $candidates = $candidateRoster->get($position->id, collect());
                    $votesAllowed = max(1, (int) ($position->votes_allowed ?? 1));
                    $votesToCast = min($votesAllowed, $candidates->count());

                    $selectedCandidates = $this->selectCandidatesWeighted($candidates, $votesToCast);

                    foreach ($selectedCandidates as $index => $candidate) {
                        Vote::query()->create([
                            'ballot_id' => $ballot->id,
                            'position_id' => $position->id,
                            'candidate_id' => $candidate->id,
                            'is_valid' => ! $isFlagged || $index > 0,
                        ]);
                    }
                }
            }
        });

        $election->update([
            'ballot_print_quantity' => $totalBallots,
        ]);

        $this->command?->info("VoteVisualizationSeeder: Generated {$totalBallots} scanned ballots, 3 partylists, and 8+ independent candidates for election #{$election->id}.");
    }

    /**
     * @return Collection<int, Collection<int, Candidate>>
     */
    private function seedCrowdedCandidates(Collection $positions): Collection
    {
        $palette = collect(config('candidate_colors.palette', []));
        $partyDefinitions = [
            ['name' => 'Unity', 'color' => $palette->get(0, '#EF4444')],
            ['name' => 'Forward', 'color' => $palette->get(10, '#3B82F6')],
            ['name' => 'Progress', 'color' => $palette->get(22, '#7C3AED')],
        ];

        $independentColors = $palette
            ->reject(fn ($color, $index) => in_array($index, [0, 10, 22], true))
            ->values();

        $partyRoster = collect();

        foreach ($positions as $position) {
            $positionCandidates = collect();

            foreach ($partyDefinitions as $definition) {
                $positionCandidates->push(Candidate::query()->create([
                    'position_id' => $position->id,
                    'name' => $this->buildPartyCandidateName($definition['name'], $position->name),
                    'party' => $definition['name'],
                    'color_code' => $definition['color'],
                    'is_active' => true,
                ]));
            }

            $partyRoster->put($position->id, $positionCandidates);
        }

        $independentNames = [
            'Avery Santos', 'Blake Navarro', 'Cameron Diaz', 'Dakota Reyes',
            'Emerson Cruz', 'Finley Tan', 'Gray Lim', 'Harper Yu',
        ];

        $positionCycle = $positions->values();

        foreach ($independentNames as $index => $name) {
            $position = $positionCycle[$index % $positionCycle->count()];
            $color = $independentColors->get($index, $independentColors->get($index % max(1, $independentColors->count()), '#64748B'));

            $candidate = Candidate::query()->create([
                'position_id' => $position->id,
                'name' => $name,
                'party' => null,
                'color_code' => $color,
                'is_active' => true,
            ]);

            $partyRoster->put($position->id, $partyRoster->get($position->id)->push($candidate));
        }

        return $partyRoster->map(fn (Collection $candidates) => $candidates->values());
    }

    private function buildPartyCandidateName(string $party, string $positionName): string
    {
        $positionSlug = Str::of($positionName)->lower()->replace([' ', '-'], ' ')->title()->replace(' ', '');

        return sprintf('%s %s', $party, $positionSlug);
    }

    /**
     * @return Collection<int, \App\Models\Candidate>
     */
    private function selectCandidatesWeighted(Collection $candidates, int $count): Collection
    {
        $pool = $candidates->values();
        $selected = collect();

        while ($selected->count() < $count && $pool->isNotEmpty()) {
            $totalWeight = 0;

            foreach ($pool as $index => $candidate) {
                $baseWeight = max(1, $pool->count() - $index);
                $totalWeight += $candidate->party ? $baseWeight + 6 : $baseWeight + 2;
            }

            $pick = random_int(1, max(1, $totalWeight));
            $running = 0;
            $chosenIndex = 0;

            foreach ($pool as $index => $candidate) {
                $running += $candidate->party
                    ? max(1, $pool->count() - $index) + 6
                    : max(1, $pool->count() - $index) + 2;

                if ($pick <= $running) {
                    $chosenIndex = $index;
                    break;
                }
            }

            $selected->push($pool->get($chosenIndex));
            $pool = $pool->forget($chosenIndex)->values();
        }

        return $selected;
    }
}
