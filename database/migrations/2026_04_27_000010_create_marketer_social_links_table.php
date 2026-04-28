<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketer_social_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 40); // instagram, tiktok, youtube, twitter, facebook, telegram
            $table->string('handle', 120);
            $table->string('profile_url', 500)->nullable();
            $table->unsignedBigInteger('follower_count')->default(0);
            $table->boolean('verified')->default(false); // set by admin
            $table->timestamps();

            $table->unique(['user_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketer_social_links');
    }
};
