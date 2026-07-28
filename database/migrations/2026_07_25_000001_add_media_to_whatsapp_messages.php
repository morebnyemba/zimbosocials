<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keep a local copy of every media message so the console can show it.
 *
 * Meta's media URLs are short-lived and need the bearer token, and media ids
 * expire (~30 days), so the raw webhook payload alone can never be replayed —
 * without this, a customer's screenshot or voice note is unviewable minutes
 * after it arrives.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table): void {
            $table->string('media_url')->nullable()->after('body');
            $table->string('media_mime', 100)->nullable()->after('media_url');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table): void {
            $table->dropColumn(['media_url', 'media_mime']);
        });
    }
};
