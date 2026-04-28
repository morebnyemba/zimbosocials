<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Only add indexes that don't already exist
        Schema::table('orders', function (Blueprint $table) {
            if (!$this->hasIndex('orders', 'orders_service_id_created_at_index')) {
                $table->index(['service_id', 'created_at']);
            }
            if (!$this->hasIndex('orders', 'orders_external_order_id_index')) {
                $table->index('external_order_id');
            }
        });

        Schema::table('transactions', function (Blueprint $table) {
            if (!$this->hasIndex('transactions', 'transactions_user_id_type_status_index')) {
                $table->index(['user_id', 'type', 'status']);
            }
            if (!$this->hasIndex('transactions', 'transactions_reference_index')) {
                $table->index('reference');
            }
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        try {
            $indexes = Schema::getIndexes($table);
            foreach ($indexes as $index) {
                if ($index['name'] === $indexName) {
                    return true;
                }
            }
        } catch (\Throwable) {
            // Fallback: assume it doesn't exist
        }
        return false;
    }

    public function down(): void
    {
        // No-op: don't drop indexes that may have existed before this migration
    }
};
