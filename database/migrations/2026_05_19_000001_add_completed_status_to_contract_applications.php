<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('contract_applications', function (Blueprint $table) {
                $table->string('status', 20)->default('pending')->change();
            });

            return;
        }

        DB::statement("ALTER TABLE contract_applications MODIFY status ENUM('pending','approved','completed','denied','ignored') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::table('contract_applications')
            ->where('status', 'completed')
            ->update(['status' => 'approved']);

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('contract_applications', function (Blueprint $table) {
                $table->string('status', 20)->default('pending')->change();
            });

            return;
        }
        DB::statement("ALTER TABLE contract_applications MODIFY status ENUM('pending','approved','denied','ignored') NOT NULL DEFAULT 'pending'");
    }
};
