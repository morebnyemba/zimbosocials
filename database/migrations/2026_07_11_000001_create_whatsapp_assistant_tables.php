<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WhatsApp conversational assistant — core schema.
 *
 * Ported from the v26 SMM panel's `whatsapp_assistant_schema.sql`, adapted to
 * this app: identity binds to `users.id` (not v26's `clients.client_id`), and
 * deferred work rides the existing Laravel `notifications` queue rather than a
 * bespoke `whatsapp_jobs` spine.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Phone <-> user identity binding.
        Schema::create('whatsapp_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('wa_phone', 20)->unique();          // E.164 digits, no leading '+'
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('link_status', ['guest', 'pending_link', 'linked'])->default('guest');
            $table->string('display_name')->nullable();
            $table->string('link_otp', 10)->nullable();
            $table->timestamp('link_otp_expires')->nullable();
            $table->unsignedTinyInteger('link_attempts')->default(0);
            $table->boolean('opted_in')->default(true);
            $table->timestamp('agent_handoff_until')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index('link_status');
        });

        // 2. Current flow/state per phone (one active session per number).
        Schema::create('whatsapp_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('wa_phone', 20)->unique();
            $table->string('current_flow', 64)->nullable();
            $table->string('current_state', 64)->nullable();
            $table->json('state_stack')->nullable();
            $table->json('context')->nullable();
            $table->enum('status', ['active', 'idle', 'expired', 'completed'])->default('active');
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('last_activity')->useCurrent();
            $table->timestamp('expires_at')->nullable();

            $table->index('status');
            $table->index('last_activity');
        });

        // 3. Unified transcript + intent-routing metadata.
        Schema::create('whatsapp_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('wa_phone', 20);
            $table->enum('direction', ['in', 'out']);
            $table->string('wa_message_id', 128)->nullable()->unique();
            $table->string('msg_type', 32)->default('text');
            $table->text('body')->nullable();
            $table->json('payload')->nullable();
            $table->string('flow', 64)->nullable();
            $table->enum('handled_by', ['command', 'flow', 'menu', 'rule', 'keyword', 'intent', 'kb', 'ai', 'agent', 'system'])->nullable();
            $table->string('intent', 64)->nullable();
            $table->decimal('confidence', 4, 3)->nullable();
            $table->boolean('ai_used')->default(false);
            $table->string('delivery_status', 20)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['wa_phone', 'created_at']);
            $table->index('handled_by');
            $table->index('flow');
        });

        // 4. Deterministic FAQ answers consulted before falling back to AI.
        Schema::create('whatsapp_knowledge_base', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('question');
            $table->text('answer');
            $table->string('keywords', 512)->nullable();
            $table->string('category', 64)->nullable();
            $table->string('locale', 10)->default('en');
            $table->boolean('status')->default(true);
            $table->unsignedInteger('hits')->default(0);
            $table->timestamps();

            $table->index('category');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_knowledge_base');
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_sessions');
        Schema::dropIfExists('whatsapp_accounts');
    }
};
