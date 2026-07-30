<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-day AI token usage, so spend is a number on a dashboard rather than a
 * surprise on a bill.
 *
 * Rolled up by day and model rather than stored per request: the interesting
 * questions are "what is this costing" and "is the cached prefix actually being
 * discounted", and neither needs a row per message.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage', function (Blueprint $table): void {
            $table->id();
            $table->date('day')->index();
            $table->string('model', 64);
            $table->unsignedInteger('requests')->default(0);
            $table->unsignedBigInteger('prompt_tokens')->default(0);
            // The part of prompt_tokens served from cache — billed far cheaper.
            // If this stays at zero the caching is not working, which is worth
            // seeing plainly.
            $table->unsignedBigInteger('cached_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->timestamps();

            $table->unique(['day', 'model']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage');
    }
};
