<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Round-number promo bundles — "3,000 Facebook Followers for $12" — which sell
 * far better on WhatsApp than a per-1,000 rate card. A bundle is an exact
 * quantity of one service at a flat price; when a customer orders that exact
 * quantity, the bundle price replaces the calculated charge everywhere (quote,
 * confirm card and the actual debit all read from Service::calculateCharge).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_bundles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('price', 10, 2);
            $table->string('label')->nullable();      // e.g. "Best value"
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            // One bundle per exact quantity of a service.
            $table->unique(['service_id', 'quantity']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_bundles');
    }
};
