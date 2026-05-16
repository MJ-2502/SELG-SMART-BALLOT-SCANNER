<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            // Drop indexes to avoid name collisions while rebuilding the table.
            DB::statement('DROP INDEX IF EXISTS ballots_election_id_ballot_number_unique');
            DB::statement('DROP INDEX IF EXISTS ballots_uuid_unique');
            DB::statement('DROP INDEX IF EXISTS ballots_image_hash_unique');

            Schema::disableForeignKeyConstraints();

            Schema::create('ballots_new', function (Blueprint $table) {
                $table->id();
                $table->foreignId('election_id')->nullable()->constrained('elections')->nullOnDelete();
                $table->unsignedInteger('ballot_number')->nullable();
                $table->uuid('uuid');
                $table->string('image_hash')->nullable();
                $table->string('image_path')->nullable();
                $table->timestamp('scanned_at')->nullable();
                $table->foreignId('scanned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status')->default('pending');
                $table->timestamps();

                $table->unique('uuid', 'ballots_uuid_unique');
                $table->unique('image_hash', 'ballots_image_hash_unique');
                $table->unique(['election_id', 'ballot_number'], 'ballots_election_id_ballot_number_unique');
            });

            DB::statement('INSERT INTO ballots_new (id, election_id, ballot_number, uuid, image_hash, image_path, scanned_at, scanned_by, status, created_at, updated_at)
                SELECT id, election_id, ballot_number, uuid, image_hash, image_path, scanned_at, scanned_by, status, created_at, updated_at FROM ballots');

            Schema::drop('ballots');
            Schema::rename('ballots_new', 'ballots');
            Schema::enableForeignKeyConstraints();
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE ballots MODIFY scanned_at TIMESTAMP NULL DEFAULT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE ballots ALTER COLUMN scanned_at DROP DEFAULT');
            DB::statement('ALTER TABLE ballots ALTER COLUMN scanned_at DROP NOT NULL');
        } elseif ($driver === 'sqlsrv') {
            DB::statement('ALTER TABLE ballots ALTER COLUMN scanned_at DATETIME NULL');
        } else {
            Schema::table('ballots', function (Blueprint $table) {
                $table->timestamp('scanned_at')->nullable()->change();
            });
        }

        DB::table('ballots')
            ->where('status', 'pending')
            ->update([
                'scanned_at' => null,
                'scanned_by' => null,
            ]);
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            Schema::disableForeignKeyConstraints();

            Schema::create('ballots_new', function (Blueprint $table) {
                $table->id();
                $table->foreignId('election_id')->nullable()->constrained('elections')->nullOnDelete();
                $table->unsignedInteger('ballot_number')->nullable();
                $table->uuid('uuid');
                $table->string('image_hash')->nullable();
                $table->string('image_path')->nullable();
                $table->timestamp('scanned_at')->useCurrent();
                $table->foreignId('scanned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status')->default('pending');
                $table->timestamps();

                $table->unique('uuid', 'ballots_uuid_unique');
                $table->unique('image_hash', 'ballots_image_hash_unique');
                $table->unique(['election_id', 'ballot_number'], 'ballots_election_id_ballot_number_unique');
            });

            DB::statement('INSERT INTO ballots_new (id, election_id, ballot_number, uuid, image_hash, image_path, scanned_at, scanned_by, status, created_at, updated_at)
                SELECT id, election_id, ballot_number, uuid, image_hash, image_path, scanned_at, scanned_by, status, created_at, updated_at FROM ballots');

            Schema::drop('ballots');
            Schema::rename('ballots_new', 'ballots');
            Schema::enableForeignKeyConstraints();
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE ballots MODIFY scanned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE ballots ALTER COLUMN scanned_at SET DEFAULT CURRENT_TIMESTAMP');
            DB::statement('ALTER TABLE ballots ALTER COLUMN scanned_at SET NOT NULL');
        } elseif ($driver === 'sqlsrv') {
            DB::statement('ALTER TABLE ballots ALTER COLUMN scanned_at DATETIME NOT NULL');
        } else {
            Schema::table('ballots', function (Blueprint $table) {
                $table->timestamp('scanned_at')->useCurrent()->nullable(false)->change();
            });
        }
    }
};
