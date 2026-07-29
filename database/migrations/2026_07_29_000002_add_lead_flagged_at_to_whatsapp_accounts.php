<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remember that a lead has already been raised with the team.
 *
 * This lived in the cache with a one-hour TTL, which meant the same dropped
 * lead was reported again every hour for as long as it stayed inside the
 * look-back window — and any deploy running `optimize:clear` wiped the record
 * entirely and re-reported every lead at once. An alert channel that repeats
 * itself is one people stop reading.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table): void {
            $table->timestamp('lead_flagged_at')->nullable()->after('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table): void {
            $table->dropColumn('lead_flagged_at');
        });
    }
};
