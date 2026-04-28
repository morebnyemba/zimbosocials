<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('platform', 80)->nullable();
            $table->text('description');
            $table->decimal('budget', 10, 2)->nullable();
            $table->unsignedSmallInteger('slots')->default(1);
            $table->date('deadline_at')->nullable();
            $table->enum('status', ['open', 'filled', 'closed'])->default('open');
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('created_at');
        });

        Schema::create('contract_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_contract_id')->constrained('business_contracts')->cascadeOnDelete();
            $table->foreignId('marketer_id')->constrained('users')->cascadeOnDelete();
            $table->text('pitch')->nullable();
            $table->enum('status', ['pending', 'approved', 'denied', 'ignored'])->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['business_contract_id', 'marketer_id']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_applications');
        Schema::dropIfExists('business_contracts');
    }
};
