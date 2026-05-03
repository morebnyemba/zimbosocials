<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketer_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_contract_id')->constrained('business_contracts')->cascadeOnDelete();
            $table->foreignId('contract_application_id')->unique()->constrained('contract_applications')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();   // business user
            $table->foreignId('marketer_id')->constrained('users')->cascadeOnDelete();
            $table->tinyInteger('rating');      // 1–5
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index('marketer_id');
            $table->index(['marketer_id', 'rating']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketer_reviews');
    }
};
