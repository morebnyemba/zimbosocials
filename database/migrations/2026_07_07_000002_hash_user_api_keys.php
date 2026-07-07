<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reseller API keys were stored (and shared to the frontend) in plaintext —
 * a database leak exposed every key. Store only a SHA-256 hash plus the last
 * four characters for display; the full key is shown once at generation.
 * Existing keys are hashed in place so current integrations keep working.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('api_key_hash', 64)->nullable()->unique()->after('api_key');
            $table->string('api_key_last4', 4)->nullable()->after('api_key_hash');
        });

        DB::table('users')
            ->whereNotNull('api_key')
            ->orderBy('id')
            ->chunkById(200, function ($users): void {
                foreach ($users as $user) {
                    DB::table('users')->where('id', $user->id)->update([
                        'api_key_hash' => hash('sha256', $user->api_key),
                        'api_key_last4' => substr($user->api_key, -4),
                        'api_key' => null,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['api_key_hash', 'api_key_last4']);
        });
    }
};
