<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ballot_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ballot_id')->constrained('ballots')->cascadeOnDelete();
            $table->string('reason', 40);
            $table->foreignId('flagged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('flagged_at')->useCurrent();
            $table->timestamps();

            $table->index(['ballot_id', 'reason']);
            $table->index('flagged_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ballot_flags');
    }
};
