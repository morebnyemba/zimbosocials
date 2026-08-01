<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Benefits now escalate with the higher prices: the AI video moved down to
 * the 3-day tier (was 1 week+), and every video tier now also promises a
 * mid-campaign progress update — the longer tiers add a choice of video
 * concepts, priority setup, and a wrap-up summary. See config/adverts.php.
 */
return new class extends Migration
{
    private const TITLE = 'Sponsored adverts';
    private const MARKER = 'custom video advert we make for you';

    public function up(): void
    {
        if (! Schema::hasTable('whatsapp_knowledge_base')) {
            return;
        }

        $row = DB::table('whatsapp_knowledge_base')->where('title', self::TITLE)->first();
        if (! $row || ! is_string($row->answer)) {
            return;
        }
        // The marker text is gone once this migration is superseded by an
        // admin edit — only touch our own auto-generated copy.
        if (! str_contains($row->answer, self::MARKER) && ! str_contains($row->answer, 'includes an AI video advert')) {
            return;
        }

        DB::table('whatsapp_knowledge_base')->where('id', $row->id)->update([
            'answer' => "Yes! Alongside growing your page, we run *sponsored adverts* on Facebook & Instagram that put your business in front of new customers.\n\n"
                ."Pick the run that suits you:\n"
                ."• *\$7* — 1 day (a quick test, we boost a post you have)\n"
                ."• *\$17* — 3 days 🎬 *includes an AI video advert* — most people start here\n"
                ."• *\$30* — 1 week 🎬 video + a progress update partway through\n"
                ."• *\$50* — 2 weeks 🎬 video (pick from 2 concepts) + a progress update\n"
                ."• *\$75* — 1 month 🎬 video (pick from 2 concepts) + priority setup + a progress update + a wrap-up summary\n\n"
                .'Just pick a package and pay — our team then messages you to get your details (what you\'re promoting, your page, the areas to target) and sets it all up.',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // No-op.
    }
};
