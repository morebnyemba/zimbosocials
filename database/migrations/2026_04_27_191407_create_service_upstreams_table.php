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
        Schema::create('service_upstreams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('upstream_provider_id')->constrained()->cascadeOnDelete();
            $table->string('external_service_id');
            $table->decimal('external_rate', 10, 4)->default(0);
            $table->integer('priority')->default(1); // 1 = first choice, 2 = fallback, etc.
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['service_id', 'upstream_provider_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_upstreams');
    }
};
