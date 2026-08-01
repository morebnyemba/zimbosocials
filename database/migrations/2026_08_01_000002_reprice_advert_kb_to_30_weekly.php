<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Advert packages repriced again, off a $30/week anchor (was $25 — see the
 * 2026-08-01_000001 migration). Existing contacts keep the $25 price for a
 * grace window (AdvertBooking::priceFor(), config/adverts.php); this only
 * updates the FAQ copy, which always shows the current headline price.
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
        if (! $row || ! is_string($row->answer) || ! str_contains($row->answer, self::MARKER)) {
            return;
        }

        DB::table('whatsapp_knowledge_base')->where('id', $row->id)->update([
            'answer' => "Yes! Alongside growing your page, we run *sponsored adverts* on Facebook & Instagram that put your business in front of new customers.\n\n"
                ."Pick the run that suits you:\n"
                ."• *\$7* — 1 day (a quick test, we boost a post you have)\n"
                ."• *\$17* — 3 days (boost-only, most people start here)\n"
                ."• *\$30* — 1 week 🎬 *includes an AI video advert*\n"
                ."• *\$50* — 2 weeks 🎬 *includes an AI video advert*\n"
                ."• *\$75* — 1 month 🎬 *AI video + maximum reach*\n\n"
                .'Just pick a package and pay — our team then messages you to get your details (what you\'re promoting, your page, the areas to target) and sets it all up.',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // No-op.
    }
};
