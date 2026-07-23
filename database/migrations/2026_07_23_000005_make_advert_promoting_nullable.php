<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The advert booking now only captures the package + payment — what the
 * customer is promoting (and their page, target areas) is gathered by the team
 * afterwards. So `promoting` is no longer set at booking time; make it nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advert_bookings', function (Blueprint $table): void {
            $table->text('promoting')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Leave nullable — new rows may have no promoting text.
    }
};
