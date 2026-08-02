<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-off account & advertising support services — helping someone run their
 * OWN ads, or recover a broken/banned account. Ships as a migration so it
 * lands on deploy without anyone remembering to seed it. Idempotent on
 * title — an admin's later edits in WhatsApp -> Knowledge Base are preserved.
 * See config/extra_services.php for the prices this must stay in step with.
 */
return new class extends Migration
{
    private const TITLE = 'Account & advertising support';

    public function up(): void
    {
        if (! Schema::hasTable('whatsapp_knowledge_base')) {
            return;
        }

        if (DB::table('whatsapp_knowledge_base')->where('title', self::TITLE)->exists()) {
            return;
        }

        DB::table('whatsapp_knowledge_base')->insert([
            'title' => self::TITLE,
            'question' => 'Can you teach me to advertise myself, ads account not working, fix my ads account, '
                .'facebook account hacked disabled banned, whatsapp account banned, instagram account hacked, '
                .'recover my account, unban my account, advertising training setup',
            'answer' => "We also help with a few one-off things outside our usual growth packages:\n\n"
                ."• *\$20* — Advertising setup & training _(want to run your own Facebook/Instagram ads? We set up your ad "
                ."account and show you how)_\n"
                ."• *\$5* — Ads account fix _(your ads account isn't working properly — we diagnose and fix it)_\n"
                ."• *\$10* — Account recovery & unbanning _(Facebook, WhatsApp or Instagram account hacked, disabled or "
                ."banned — we help you get back in)_\n\n"
                .'Reply *support* and our team will get you sorted.',
            'keywords' => 'advertise myself advertising training setup teach learn run my own ads ad account fix broken '
                .'not working restricted facebook hacked disabled banned whatsapp banned instagram hacked banned recover '
                .'recovery unban unbanning account access',
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
