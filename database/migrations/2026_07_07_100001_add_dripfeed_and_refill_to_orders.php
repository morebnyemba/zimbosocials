<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drip-feed ordering (quantity per run × runs, spaced by interval minutes)
 * and refill tracking. Services already carried is_dripfeed/is_refill flags,
 * but neither feature existed in the order pipeline.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedSmallInteger('runs')->nullable()->after('quantity');
            $table->unsignedSmallInteger('interval_minutes')->nullable()->after('runs');
            $table->timestamp('refill_requested_at')->nullable()->after('completed_at');
            $table->string('external_refill_id', 64)->nullable()->after('refill_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['runs', 'interval_minutes', 'refill_requested_at', 'external_refill_id']);
        });
    }
};
