<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            if (! Schema::hasColumn('services', 'name_nd')) {
                $table->string('name_nd')->nullable()->after('name_sn'); // Ndebele name
            }
            if (! Schema::hasColumn('services', 'description_nd')) {
                $table->string('description_nd')->nullable()->after('description_sn'); // Ndebele description
            }
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['name_nd', 'description_nd']);
        });
    }
};
