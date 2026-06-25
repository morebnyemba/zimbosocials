<?php

// database/migrations/2024_01_01_000003_create_orders_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->string('link');
            $table->unsignedInteger('quantity');
            $table->decimal('charge', 10, 4);           // actual amount deducted
            $table->decimal('rate_at_order', 8, 4);     // rate snapshot at time of order
            $table->enum('status', [
                'pending',
                'processing',
                'in_progress',
                'completed',
                'partial',
                'cancelled',
                'refunded',
            ])->default('pending');
            $table->unsignedBigInteger('start_count')->nullable(); // followers before order
            $table->unsignedBigInteger('remains')->nullable();     // remaining to deliver
            $table->string('external_order_id')->nullable();       // ID from upstream provider
            $table->unsignedSmallInteger('push_attempts')->default(0);
            $table->boolean('pushed_to_upstream')->default(false);
            $table->timestamp('pushed_at')->nullable();
            $table->text('upstream_last_error')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
