<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DB-backed WhatsApp templates, editable from the admin panel. Seeded from
     * config/whatsapp-templates.php (which stays as the pristine default set);
     * once rows exist, AppServiceProvider overrides the config with them at boot
     * so every consumer (send job, sync command, fallback text) uses the DB.
     */
    public function up(): void
    {
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('category')->default('UTILITY'); // UTILITY | MARKETING | AUTHENTICATION
            $table->text('body');
            $table->json('params')->nullable();   // ordered param labels, e.g. ["user_name","amount"]
            $table->string('header')->nullable();
            $table->string('footer')->nullable();
            $table->json('buttons')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        foreach ((array) config('whatsapp-templates.templates', []) as $name => $tpl) {
            DB::table('whatsapp_templates')->insertOrIgnore([
                'name' => $name,
                'category' => $tpl['category'] ?? 'UTILITY',
                'body' => $tpl['body'] ?? '',
                'params' => json_encode($tpl['params'] ?? []),
                'header' => $tpl['header'] ?? null,
                'footer' => $tpl['footer'] ?? null,
                'buttons' => json_encode($tpl['buttons'] ?? []),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_templates');
    }
};
