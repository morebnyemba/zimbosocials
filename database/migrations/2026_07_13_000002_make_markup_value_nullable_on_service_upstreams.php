<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * NULL markup_value now means "margin not established yet" — the nightly
     * sync derives it from the service's CURRENT price the first time it sees
     * a real provider cost, instead of applying a default that would reset the
     * admin's price. New/repointed pivots start as NULL.
     */
    public function up(): void
    {
        Schema::table('service_upstreams', function (Blueprint $table) {
            $table->decimal('markup_value', 10, 4)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('service_upstreams', function (Blueprint $table) {
            $table->decimal('markup_value', 10, 4)->default(20)->change();
        });
    }
};
