<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            Schema::table('transactions', function (Blueprint $table) {
                $table->string('type', 50)->change();
            });

            return;
        }

        DB::statement("ALTER TABLE transactions MODIFY type ENUM('deposit','order_charge','refund','adjustment','bonus','contract_payout','contract_earning') NOT NULL");
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        DB::table('transactions')
            ->where('type', 'contract_payout')
            ->update(['type' => 'adjustment']);

        DB::table('transactions')
            ->where('type', 'contract_earning')
            ->update(['type' => 'bonus']);

        if ($driver === 'sqlite') {
            Schema::table('transactions', function (Blueprint $table) {
                $table->string('type', 50)->change();
            });

            return;
        }

        DB::statement("ALTER TABLE transactions MODIFY type ENUM('deposit','order_charge','refund','adjustment','bonus') NOT NULL");
    }
};
