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
        Schema::create('social_platform_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 50);
            $table->string('metric_key', 50);
            $table->unsignedBigInteger('value')->default(0);
            $table->string('source', 20)->default('manual'); // api or manual
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'platform', 'metric_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_platform_stats');
    }
};
