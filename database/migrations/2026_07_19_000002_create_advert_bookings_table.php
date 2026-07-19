<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A paid sponsored-advert booking. Unlike catalogue orders these are priced
 * flat per week and fulfilled by a human (the team sets the campaign up), so
 * they never touch the upstream dispatcher — they land as 'pending_setup' for
 * an admin to action.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advert_bookings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('wa_phone', 32)->nullable();
            $table->string('package', 32);                 // starter | standard | max
            $table->unsignedSmallInteger('weeks');
            $table->decimal('weekly_price', 10, 2);
            $table->decimal('total', 10, 2);               // amount actually charged
            $table->text('promoting');                     // what they're advertising
            $table->string('target_link', 500)->nullable();
            $table->string('status', 32)->default('pending_setup');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advert_bookings');
    }
};
