<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->after('name')->unique();
            $table->string('google_id')->nullable()->after('email')->unique();
        });

        $users = DB::table('users')->select(['id', 'name', 'email', 'username'])->get();
        $usedUsernames = DB::table('users')
            ->whereNotNull('username')
            ->pluck('username')
            ->map(fn ($username) => strtolower((string) $username))
            ->all();

        foreach ($users as $user) {
            if (! empty($user->username)) {
                continue;
            }

            $seed = $user->email ? Str::before($user->email, '@') : ($user->name ?: 'user');
            $base = Str::of(Str::lower($seed))
                ->replaceMatches('/[^a-z0-9_\-]/', '')
                ->trim('_-')
                ->value();

            if ($base === '') {
                $base = 'user';
            }

            $candidate = $base;
            $counter = 1;

            while (in_array($candidate, $usedUsernames, true)) {
                $counter++;
                $candidate = $base.$counter;
            }

            $usedUsernames[] = $candidate;

            DB::table('users')
                ->where('id', $user->id)
                ->update(['username' => $candidate]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_username_unique');
            $table->dropUnique('users_google_id_unique');
            $table->dropColumn(['username', 'google_id']);
        });
    }
};
