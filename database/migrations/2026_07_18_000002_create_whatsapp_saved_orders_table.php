<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An order a WhatsApp customer built but couldn't place yet for lack of funds.
 * Previously kept only in the cache (fine for the resume-after-deposit path,
 * but not enumerable); a durable row lets a scheduled job find customers who
 * stashed an order and never topped up, and send one top-up reminder. Cleared
 * when the order is placed, the deposit fails for good, or it's abandoned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_saved_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('wa_phone', 32);
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('link', 500);
            $table->unsignedInteger('quantity');
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();

            $table->index('reminded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_saved_orders');
    }
};
