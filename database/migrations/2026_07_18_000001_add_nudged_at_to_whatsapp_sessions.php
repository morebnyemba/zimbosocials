<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a customer goes quiet mid-flow, a scheduled job sends one gentle
 * "still there?" nudge to pull them back before they drop off. nudged_at
 * records that we've already nudged this stall; it's cleared the moment they
 * act again, so a later stall can be nudged afresh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table): void {
            $table->timestamp('nudged_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table): void {
            $table->dropColumn('nudged_at');
        });
    }
};
