<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marks whether a user has chosen their own username/password/email, as
 * opposed to the auto-generated ones WhatsAppRegistrar sets for a WhatsApp
 * sign-up (random password, synthetic placeholder email for silent
 * auto-registration, a derived-or-random username). Null means "still on the
 * generated details" — the weblogin flow routes them to /account/setup to
 * replace them. Backfilled to now() for every existing row so no current
 * user is retroactively nagged; only WhatsApp accounts created AFTER this
 * migration start out null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('credentials_set_at')->nullable()->after('password');
        });

        DB::table('users')->whereNull('credentials_set_at')->update(['credentials_set_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('credentials_set_at');
        });
    }
};
