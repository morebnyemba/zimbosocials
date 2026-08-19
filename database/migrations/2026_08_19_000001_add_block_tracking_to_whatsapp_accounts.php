<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Four separate automated systems (idle-customer nudges, saved-order
 * reminders, marketing broadcasts, and — indirectly — lead alerts that
 * prompt a human to message in) can each independently decide a contact
 * deserves a message right now, with no shared memory of what the OTHER
 * systems already sent them. That's the leading driver of the WhatsApp
 * block rate: a contact gets hit repeatedly across systems in the same
 * day even though every individual system's own checks passed.
 *
 * last_automated_message_at gives every automated sender a shared cooldown
 * to check against. blocked_at / consecutive_send_failures let repeated
 * delivery failures (Meta's `failed` status callback) auto-suppress further
 * automated sends to a contact who appears to be blocking/unreachable,
 * instead of continuing to hammer a dead number until someone notices.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table): void {
            $table->timestamp('last_automated_message_at')->nullable()->after('lead_flagged_at');
            $table->timestamp('blocked_at')->nullable()->after('last_automated_message_at');
            $table->unsignedTinyInteger('consecutive_send_failures')->default(0)->after('blocked_at');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table): void {
            $table->dropColumn(['last_automated_message_at', 'blocked_at', 'consecutive_send_failures']);
        });
    }
};
