<?php

// database/migrations/2024_01_01_000004_create_transactions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['deposit', 'order_charge', 'refund', 'adjustment', 'bonus']);
            $table->decimal('amount', 10, 4);           // positive = credit, negative = debit
            $table->decimal('balance_before', 10, 4);
            $table->decimal('balance_after', 10, 4);
            $table->string('method')->nullable();        // EcoCash, PayPal, Crypto, etc.
            $table->string('reference')->nullable();     // payment reference / txn ID
            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
