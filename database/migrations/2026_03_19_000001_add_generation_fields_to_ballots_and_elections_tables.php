<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('elections', 'ballot_print_quantity')) {
            Schema::table('elections', function (Blueprint $table) {
                $table->unsignedInteger('ballot_print_quantity')->default(0)->after('election_date');
            });
        }

        if (! Schema::hasColumn('ballots', 'election_id')) {
            Schema::table('ballots', function (Blueprint $table) {
                $table->foreignId('election_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('elections')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('ballots', 'ballot_number')) {
            Schema::table('ballots', function (Blueprint $table) {
                $table->unsignedInteger('ballot_number')->nullable()->after('election_id');
            });
        }

        $indexExists = Schema::hasIndex('ballots', 'ballots_election_id_ballot_number_unique');

        if (! $indexExists && Schema::hasColumn('ballots', 'election_id') && Schema::hasColumn('ballots', 'ballot_number')) {
            Schema::table('ballots', function (Blueprint $table) {
                $table->unique(['election_id', 'ballot_number'], 'ballots_election_id_ballot_number_unique');
            });
        }
    }

    public function down(): void
    {
        $indexExists = Schema::hasIndex('ballots', 'ballots_election_id_ballot_number_unique');

        if ($indexExists) {
            Schema::table('ballots', function (Blueprint $table) {
                $table->dropUnique('ballots_election_id_ballot_number_unique');
            });
        }

        if (Schema::hasColumn('ballots', 'election_id')) {
            Schema::table('ballots', function (Blueprint $table) {
                $table->dropConstrainedForeignId('election_id');
            });
        }

        if (Schema::hasColumn('ballots', 'ballot_number')) {
            Schema::table('ballots', function (Blueprint $table) {
                $table->dropColumn('ballot_number');
            });
        }

        if (Schema::hasColumn('elections', 'ballot_print_quantity')) {
            Schema::table('elections', function (Blueprint $table) {
                $table->dropColumn('ballot_print_quantity');
            });
        }
    }
};
