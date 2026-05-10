<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropForeign(['election_id']);
            $table->unsignedBigInteger('election_id')->nullable()->change();
            $table->foreign('election_id')->references('id')->on('elections')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropForeign(['election_id']);
            $table->unsignedBigInteger('election_id')->nullable(false)->change();
            $table->foreign('election_id')->references('id')->on('elections')->cascadeOnDelete();
        });
    }
};