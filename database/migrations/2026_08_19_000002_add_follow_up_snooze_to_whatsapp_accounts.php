<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a contact tell the bot when THEY want to be followed up with — "remind
 * me tomorrow", "check back in a week", "stop following up" — instead of the
 * automated systems guessing a fixed cadence. Every automated sender already
 * gates on WhatsAppAccount::canReceiveAutomatedMessage(); this column feeds
 * into that same gate, so honouring the request took no changes to the
 * senders themselves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table): void {
            $table->timestamp('follow_up_snooze_until')->nullable()->after('consecutive_send_failures');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table): void {
            $table->dropColumn('follow_up_snooze_until');
        });
    }
};
