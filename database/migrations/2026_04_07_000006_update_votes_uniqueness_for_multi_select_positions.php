<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $existingIndexes = collect(DB::select('SHOW INDEX FROM votes'))->pluck('Key_name')->unique()->values()->all();

        Schema::table('votes', function (Blueprint $table) use ($existingIndexes) {
            if (! in_array('votes_ballot_id_index', $existingIndexes, true)) {
                $table->index('ballot_id');
            }

            if (! in_array('votes_position_id_index', $existingIndexes, true)) {
                $table->index('position_id');
            }

            if (! in_array('votes_candidate_id_index', $existingIndexes, true)) {
                $table->index('candidate_id');
            }

            if (in_array('votes_ballot_id_position_id_unique', $existingIndexes, true)) {
                $table->dropUnique(['ballot_id', 'position_id']);
            }

            if (! in_array('votes_ballot_id_position_id_candidate_id_unique', $existingIndexes, true)) {
                $table->unique(['ballot_id', 'position_id', 'candidate_id']);
            }
        });
    }

    public function down(): void
    {
        $existingIndexes = collect(DB::select('SHOW INDEX FROM votes'))->pluck('Key_name')->unique()->values()->all();

        Schema::table('votes', function (Blueprint $table) use ($existingIndexes) {
            if (in_array('votes_ballot_id_position_id_candidate_id_unique', $existingIndexes, true)) {
                $table->dropUnique(['ballot_id', 'position_id', 'candidate_id']);
            }

            if (! in_array('votes_ballot_id_position_id_unique', $existingIndexes, true)) {
                $table->unique(['ballot_id', 'position_id']);
            }
        });
    }
};
