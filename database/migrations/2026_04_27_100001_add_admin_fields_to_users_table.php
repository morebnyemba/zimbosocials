<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('admin_notes')->nullable()->after('company_name');
            $table->timestamp('last_login_at')->nullable()->after('admin_notes');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $table->string('slug')->nullable()->unique()->after('last_login_ip');
            $table->text('bio')->nullable()->after('slug');
            $table->string('profile_image_url', 1000)->nullable()->after('bio');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'admin_notes', 'last_login_at', 'last_login_ip',
                'slug', 'bio', 'profile_image_url',
            ]);
        });
    }
};
