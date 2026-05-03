<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Stores provider-specific response data from Paynow:
            // InnBucks  → { authorizationcode, authorizationexpires }
            // O'mari    → { otpreference, remoteotpurl }
            $table->json('gateway_meta')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('gateway_meta');
        });
    }
};
