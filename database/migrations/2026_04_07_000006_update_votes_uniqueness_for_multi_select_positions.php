<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasBallotIdIndex = Schema::hasIndex('votes', 'votes_ballot_id_index');
        $hasPositionIdIndex = Schema::hasIndex('votes', 'votes_position_id_index');
        $hasCandidateIdIndex = Schema::hasIndex('votes', 'votes_candidate_id_index');
        $hasBallotPositionUnique = Schema::hasIndex('votes', 'votes_ballot_id_position_id_unique');
        $hasBallotPositionCandidateUnique = Schema::hasIndex('votes', 'votes_ballot_id_position_id_candidate_id_unique');

        Schema::table('votes', function (Blueprint $table) use ($hasBallotIdIndex, $hasPositionIdIndex, $hasCandidateIdIndex, $hasBallotPositionUnique, $hasBallotPositionCandidateUnique) {
            if (! $hasBallotIdIndex) {
                $table->index('ballot_id');
            }

            if (! $hasPositionIdIndex) {
                $table->index('position_id');
            }

            if (! $hasCandidateIdIndex) {
                $table->index('candidate_id');
            }

            if ($hasBallotPositionUnique) {
                $table->dropUnique(['ballot_id', 'position_id']);
            }

            if (! $hasBallotPositionCandidateUnique) {
                $table->unique(['ballot_id', 'position_id', 'candidate_id']);
            }
        });
    }

    public function down(): void
    {
        $hasBallotPositionUnique = Schema::hasIndex('votes', 'votes_ballot_id_position_id_unique');
        $hasBallotPositionCandidateUnique = Schema::hasIndex('votes', 'votes_ballot_id_position_id_candidate_id_unique');

        Schema::table('votes', function (Blueprint $table) use ($hasBallotPositionUnique, $hasBallotPositionCandidateUnique) {
            if ($hasBallotPositionCandidateUnique) {
                $table->dropUnique(['ballot_id', 'position_id', 'candidate_id']);
            }

            if (! $hasBallotPositionUnique) {
                $table->unique(['ballot_id', 'position_id']);
            }
        });
    }
};
