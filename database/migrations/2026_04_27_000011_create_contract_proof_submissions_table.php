<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_proof_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marketer_id')->constrained('users')->cascadeOnDelete();
            $table->string('proof_url', 1000);
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending'); // pending | approved | rejected
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_proof_submissions');
    }
};
