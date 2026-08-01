<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks which of the three re-engagement checkpoints (2h/12h/23h since the
 * customer's last message) has already been sent, alongside nudged_at. A
 * single timestamp can't tell "haven't nudged yet" apart from "already sent
 * tier 2, don't repeat it" — nudge_tier is the highest tier sent so far, and
 * resets to 0 together with nudged_at the moment the customer replies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table): void {
            $table->unsignedTinyInteger('nudge_tier')->default(0)->after('nudged_at');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table): void {
            $table->dropColumn('nudge_tier');
        });
    }
};
