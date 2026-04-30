<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('name', 150);
            $table->json('subjects');
            $table->json('bodies');
            $table->json('channels');
            $table->json('filters')->nullable();
            $table->string('status', 24)->default('queued');
            $table->unsignedInteger('recipients_total')->default(0);
            $table->unsignedInteger('sent_email')->default(0);
            $table->unsignedInteger('sent_whatsapp')->default(0);
            $table->unsignedInteger('sent_in_app')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_campaigns');
    }
};
