<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The original create_settings_table migration omitted an `id` primary key, so
 * the Setting model (which assumes `id`) could not UPDATE existing rows — every
 * settings save 500'd with "Unknown column 'id'". This backfills the missing
 * primary key on installs created before the create migration was fixed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('settings', 'id')) {
            return;
        }

        // SQLite can't add a primary-key column via ALTER — rebuild the table.
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $rows = DB::table('settings')->get();

            Schema::drop('settings');
            Schema::create('settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->string('group')->default('general');
                $table->timestamps();
            });

            foreach ($rows as $row) {
                DB::table('settings')->insert((array) $row);
            }

            return;
        }

        Schema::table('settings', function (Blueprint $table) {
            $table->bigIncrements('id')->first();
        });
    }

    public function down(): void
    {
        // No-op: dropping the primary key the model depends on would re-introduce
        // the bug, so this repair is intentionally irreversible.
    }
};
