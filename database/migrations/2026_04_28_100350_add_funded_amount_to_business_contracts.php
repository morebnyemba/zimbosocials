<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('business_contracts', function (Blueprint $table) {
            $table->decimal('funded_amount', 16, 2)->default(0)->after('slots');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_contracts', function (Blueprint $table) {
            //
        });
    }
};
