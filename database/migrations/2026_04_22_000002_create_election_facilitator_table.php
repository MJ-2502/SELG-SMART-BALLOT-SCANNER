<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('election_facilitator', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained('elections')->cascadeOnDelete();
            $table->foreignId('facilitator_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['election_id', 'facilitator_id']);
        });

        DB::table('elections')
            ->whereNotNull('facilitator_id')
            ->orderBy('id')
            ->chunkById(200, function ($elections) {
                $now = now();
                $rows = [];

                foreach ($elections as $election) {
                    $rows[] = [
                        'election_id' => $election->id,
                        'facilitator_id' => $election->facilitator_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (! empty($rows)) {
                    DB::table('election_facilitator')->upsert(
                        $rows,
                        ['election_id', 'facilitator_id'],
                        ['updated_at']
                    );
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('election_facilitator');
    }
};
