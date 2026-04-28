<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('processed_by')->nullable()->after('notes');
            $table->timestamp('processed_at')->nullable()->after('processed_by');
            $table->text('admin_notes')->nullable()->after('processed_at');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['processed_by', 'processed_at', 'admin_notes']);
        });
    }
};
