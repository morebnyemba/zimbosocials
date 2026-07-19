<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed the sponsored-adverts FAQ so the assistant can quote the weekly advert
 * packages from grounded context (it is forbidden from inventing prices). Ships
 * as a migration, not just a seeder, so it lands on deploy without anyone
 * remembering to run db:seed. Idempotent on title — an admin's later edits in
 * WhatsApp → Knowledge Base are preserved.
 */
return new class extends Migration
{
    private const TITLE = 'Sponsored adverts';

    public function up(): void
    {
        if (! Schema::hasTable('whatsapp_knowledge_base')) {
            return;
        }

        $exists = DB::table('whatsapp_knowledge_base')->where('title', self::TITLE)->exists();
        if ($exists) {
            return;
        }

        DB::table('whatsapp_knowledge_base')->insert([
            'title' => self::TITLE,
            'question' => 'Do you run sponsored adverts advertising facebook instagram ads promote my business get customers how much per week',
            'answer' => "Yes! Alongside growing your page, we run *sponsored adverts* on Facebook & Instagram that put your business in front of new customers.\n\n"
                ."Weekly packages:\n"
                ."• *\$15/week* — starter\n"
                ."• *\$30/week* — standard\n"
                ."• *\$50/week* — maximum reach\n\n"
                .'The bigger the package, the more people your advert reaches. Tell me what you\'re promoting and your budget, and we\'ll set it up for you.',
            'keywords' => 'sponsored advert adverts advertising ads facebook ads instagram ads promote promotion boost campaign marketing customers sales business weekly package 15 30 50 price cost',
            'category' => 'advertising',
            'locale' => 'en',
            'status' => true,
            'hits' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('whatsapp_knowledge_base')) {
            DB::table('whatsapp_knowledge_base')->where('title', self::TITLE)->delete();
        }
    }
};
