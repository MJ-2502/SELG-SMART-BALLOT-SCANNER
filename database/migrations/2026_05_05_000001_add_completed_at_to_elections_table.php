<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('elections')) {
            return;
        }

        Schema::table('elections', function (Blueprint $table) {
            if (!Schema::hasColumn('elections', 'completed_at')) {
                $table->dateTime('completed_at')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('elections')) {
            Schema::table('elections', function (Blueprint $table) {
                if (Schema::hasColumn('elections', 'completed_at')) {
                    $table->dropColumn('completed_at');
                }
            });
        }
    }
};
