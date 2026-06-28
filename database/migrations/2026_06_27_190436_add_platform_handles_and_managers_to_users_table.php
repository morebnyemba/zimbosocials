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
        Schema::table('users', function (Blueprint $table) {
            $table->string('youtube_channel_id')->nullable()->after('account_type');
            $table->string('facebook_page_id')->nullable()->after('youtube_channel_id');
            $table->string('tiktok_username')->nullable()->after('facebook_page_id');
            $table->string('instagram_username')->nullable()->after('tiktok_username');
            $table->string('x_username')->nullable()->after('instagram_username');
            $table->string('manager_role')->nullable()->after('x_username');
            $table->foreignId('account_manager_id')->nullable()->constrained('users')->after('manager_role');
            $table->foreignId('support_manager_id')->nullable()->constrained('users')->after('account_manager_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'youtube_channel_id',
                'facebook_page_id',
                'tiktok_username',
                'instagram_username',
                'x_username',
                'manager_role',
                'account_manager_id',
                'support_manager_id',
            ]);
        });
    }
};
