<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Refresh the Sponsored adverts FAQ to the flat-price, duration-based packages
 * (a 1-day test through to a month) the assistant now quotes from. Only rewrites
 * the answer/keywords if the row still holds the old weekly copy, so an admin's
 * hand edits are left alone.
 */
return new class extends Migration
{
    private const TITLE = 'Sponsored adverts';

    public function up(): void
    {
        if (! Schema::hasTable('whatsapp_knowledge_base')) {
            return;
        }

        $row = DB::table('whatsapp_knowledge_base')->where('title', self::TITLE)->first();
        if (! $row) {
            return;
        }

        // Leave admin-edited copy alone (only replace the original weekly text).
        if (is_string($row->answer) && ! str_contains($row->answer, 'week')) {
            return;
        }

        DB::table('whatsapp_knowledge_base')->where('id', $row->id)->update([
            'answer' => "Yes! Alongside growing your page, we run *sponsored adverts* on Facebook & Instagram that put your business in front of new customers.\n\n"
                ."Pick the run that suits you:\n"
                ."• *\$5* — 1 day (a quick test)\n"
                ."• *\$10* — 3 days (most people start here)\n"
                ."• *\$20* — 1 week\n"
                ."• *\$35* — 2 weeks\n"
                ."• *\$60* — 1 month (best value, biggest reach)\n\n"
                .'Tell me what you\'re promoting and which areas to target, and we\'ll set it up for you.',
            'keywords' => 'sponsored advert adverts advertising ads facebook ads instagram ads promote promotion boost campaign marketing customers sales business day 3 days week month package 5 10 20 35 60 price cost target area',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // No-op: the previous weekly copy is superseded; no clean revert.
    }
};
