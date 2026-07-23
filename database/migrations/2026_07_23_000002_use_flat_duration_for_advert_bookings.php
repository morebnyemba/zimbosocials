<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Advert packages moved from "weekly_price × weeks" to a flat price for a fixed
 * number of days. Add a nullable `days`, and relax `weeks`/`weekly_price` to
 * nullable so old rows keep their data while new bookings just record days +
 * total. `total` stays the source of truth for what was charged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advert_bookings', function (Blueprint $table): void {
            $table->unsignedSmallInteger('days')->nullable()->after('package');
            $table->unsignedSmallInteger('weeks')->nullable()->change();
            $table->decimal('weekly_price', 10, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('advert_bookings', function (Blueprint $table): void {
            $table->dropColumn('days');
            // Leave weeks/weekly_price nullable — narrowing them could fail on
            // rows created under the new flat model.
        });
    }
};
