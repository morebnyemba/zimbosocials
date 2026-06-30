<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leaderboard_prizes', function (Blueprint $table) {
            $table->id();
            $table->string('category'); // 'referrals', 'orders', 'deposits'
            $table->unsignedInteger('rank'); // 1 = first place, 2 = second, etc.
            $table->string('title'); // e.g. '10,000 Facebook Followers'
            $table->text('description')->nullable();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->unsignedInteger('service_quantity')->nullable();
            $table->decimal('bonus_amount', 12, 4)->nullable()->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['category', 'rank']);
        });

        Schema::create('leaderboard_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('category'); // 'referrals', 'orders', 'deposits'
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('rank');
            $table->decimal('score', 12, 4)->default(0);
            $table->boolean('is_awarded')->default(false);
            $table->timestamp('awarded_at')->nullable();
            $table->foreignId('prize_id')->nullable()->constrained('leaderboard_prizes')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['year', 'month', 'category', 'user_id']);
            $table->index(['year', 'month', 'category', 'rank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leaderboard_snapshots');
        Schema::dropIfExists('leaderboard_prizes');
    }
};
