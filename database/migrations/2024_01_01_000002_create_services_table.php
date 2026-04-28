<?php
// database/migrations/2024_01_01_000002_create_services_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');                    // English name
            $table->string('name_sn');                 // Shona name
            $table->string('description')->nullable();
            $table->string('description_sn')->nullable();
            $table->string('category');                // instagram, youtube, tiktok, etc.
            $table->string('type')->default('default'); // followers, likes, views, comments
            $table->string('upstream_service_id')->nullable();
            $table->decimal('rate', 8, 4);             // price per 1000
            $table->unsignedInteger('min_qty');
            $table->unsignedInteger('max_qty');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_dripfeed')->default(false);
            $table->boolean('is_refill')->default(false);
            $table->unsignedInteger('refill_days')->default(30);
            $table->unsignedInteger('avg_time_minutes')->nullable(); // estimated delivery
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
