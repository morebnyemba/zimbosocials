<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `transactions.type` and `transactions.status` were ENUMs, so any value not in
 * the original set (e.g. type='withdrawal'/'debit', status='rejected') failed
 * with "Data truncated for column". Widen both to VARCHAR(50) so new
 * transaction types/statuses don't require a schema change each time.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            Schema::table('transactions', function (Blueprint $table) {
                $table->string('type', 50)->change();
                $table->string('status', 50)->default('pending')->change();
            });

            return;
        }

        DB::statement("ALTER TABLE transactions MODIFY type VARCHAR(50) NOT NULL");
        DB::statement("ALTER TABLE transactions MODIFY status VARCHAR(50) NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite already stored these as strings; nothing meaningful to revert.
            return;
        }

        // Restore the previous ENUM definitions (state prior to this migration).
        DB::statement("ALTER TABLE transactions MODIFY type ENUM('deposit','order_charge','refund','adjustment','bonus','contract_payout','contract_earning') NOT NULL");
        DB::statement("ALTER TABLE transactions MODIFY status ENUM('pending','completed','failed','cancelled') NOT NULL DEFAULT 'pending'");
    }
};
