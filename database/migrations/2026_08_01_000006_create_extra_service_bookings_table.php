<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A paid one-off service (advertising setup & training, ads account fix,
 * account recovery/unbanning — see config/extra_services.php). Same pattern
 * as advert_bookings: flat-priced, human-fulfilled, so it never touches the
 * upstream dispatcher — it lands as 'pending_setup' for an admin to action.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extra_service_bookings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('wa_phone', 32)->nullable();
            $table->string('item', 40);   // key into config('extra_services.items')
            $table->decimal('total', 10, 2);
            $table->string('status', 32)->default('pending_setup');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extra_service_bookings');
    }
};
