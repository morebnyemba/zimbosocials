<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who / where an advert should target — "Damafalls, Ruwa, Eastview", "mums of
 * under-5s in Harare". It's the single most important input for a local ad
 * campaign and customers volunteer it naturally, but the booking had nowhere to
 * keep it, so the ad team was set up blind.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advert_bookings', function (Blueprint $table): void {
            $table->text('target_audience')->nullable()->after('target_link');
        });
    }

    public function down(): void
    {
        Schema::table('advert_bookings', function (Blueprint $table): void {
            $table->dropColumn('target_audience');
        });
    }
};
