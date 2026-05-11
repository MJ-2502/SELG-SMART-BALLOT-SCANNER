<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("UPDATE ballots SET scanned_at = DATE_ADD(scanned_at, INTERVAL 8 HOUR) WHERE scanned_at IS NOT NULL AND status IN ('scanned', 'flagged')");
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("UPDATE ballots SET scanned_at = scanned_at + interval '8 hours' WHERE scanned_at IS NOT NULL AND status IN ('scanned', 'flagged')");
            return;
        }

        if ($driver === 'sqlite') {
            DB::statement("UPDATE ballots SET scanned_at = datetime(scanned_at, '+8 hours') WHERE scanned_at IS NOT NULL AND status IN ('scanned', 'flagged')");
            return;
        }

        if ($driver === 'sqlsrv') {
            DB::statement("UPDATE ballots SET scanned_at = DATEADD(hour, 8, scanned_at) WHERE scanned_at IS NOT NULL AND status IN ('scanned', 'flagged')");
            return;
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("UPDATE ballots SET scanned_at = DATE_ADD(scanned_at, INTERVAL -8 HOUR) WHERE scanned_at IS NOT NULL AND status IN ('scanned', 'flagged')");
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("UPDATE ballots SET scanned_at = scanned_at - interval '8 hours' WHERE scanned_at IS NOT NULL AND status IN ('scanned', 'flagged')");
            return;
        }

        if ($driver === 'sqlite') {
            DB::statement("UPDATE ballots SET scanned_at = datetime(scanned_at, '-8 hours') WHERE scanned_at IS NOT NULL AND status IN ('scanned', 'flagged')");
            return;
        }

        if ($driver === 'sqlsrv') {
            DB::statement("UPDATE ballots SET scanned_at = DATEADD(hour, -8, scanned_at) WHERE scanned_at IS NOT NULL AND status IN ('scanned', 'flagged')");
            return;
        }
    }
};
