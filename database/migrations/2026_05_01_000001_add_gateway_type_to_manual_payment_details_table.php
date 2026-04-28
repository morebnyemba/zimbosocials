<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manual_payment_details', function (Blueprint $table) {
            // null = truly manual; 'paynow' = handled via Paynow gateway
            $table->string('gateway_type', 20)->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('manual_payment_details', function (Blueprint $table) {
            $table->dropColumn('gateway_type');
        });
    }
};
