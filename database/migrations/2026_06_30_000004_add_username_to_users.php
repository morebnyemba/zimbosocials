<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a unique `username` to users. Existing users are backfilled with a
 * handle generated from their name so username login + the username-only
 * leaderboard work immediately. New signups choose their own (validated +
 * real-time availability checked).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Add the column nullable first so we can backfill before enforcing uniqueness.
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 30)->nullable()->after('name');
        });

        // 2. Backfill existing rows with a unique handle derived from the name.
        User::query()->whereNull('username')->orderBy('id')->chunkById(200, function ($users) {
            foreach ($users as $user) {
                $user->forceFill([
                    'username' => User::generateUsernameFromName((string) ($user->name ?: 'user'), $user->id),
                ])->saveQuietly();
            }
        });

        // 3. Enforce uniqueness now that every row has a value.
        Schema::table('users', function (Blueprint $table) {
            $table->unique('username');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
