<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One-tap web login links sent over WhatsApp ('weblogin' flow). Only the
 * SHA-256 hash of the raw token is stored — same pattern as Laravel's own
 * password_reset_tokens — so a leaked database row can't be replayed as a
 * login. Each token is single-use and short-lived (see WebLoginToken).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_login_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('wa_phone', 32);
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_login_tokens');
    }
};
