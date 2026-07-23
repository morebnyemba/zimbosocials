<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Note which advert packages include a made-for-you video in the FAQ the
 * assistant quotes. Only rewrites the auto-generated flat-package copy — an
 * admin's hand edits (which won't contain this exact marker) are left alone.
 */
return new class extends Migration
{
    private const TITLE = 'Sponsored adverts';
    private const MARKER = 'best value, biggest reach'; // present only in our generated copy

    public function up(): void
    {
        if (! Schema::hasTable('whatsapp_knowledge_base')) {
            return;
        }

        $row = DB::table('whatsapp_knowledge_base')->where('title', self::TITLE)->first();
        if (! $row || ! is_string($row->answer) || ! str_contains($row->answer, self::MARKER)) {
            return; // missing, or admin-edited — don't clobber
        }

        DB::table('whatsapp_knowledge_base')->where('id', $row->id)->update([
            'answer' => "Yes! Alongside growing your page, we run *sponsored adverts* on Facebook & Instagram that put your business in front of new customers.\n\n"
                ."Pick the run that suits you:\n"
                ."• *\$5* — 1 day (a quick test, we boost a post you have)\n"
                ."• *\$10* — 3 days (boost-only, most people start here)\n"
                ."• *\$20* — 1 week 🎬 *includes a custom video advert we make for you*\n"
                ."• *\$35* — 2 weeks 🎬 *includes a custom video advert*\n"
                ."• *\$60* — 1 month 🎬 *custom video + maximum reach*\n\n"
                .'Tell me what you\'re promoting and which areas to target, and we\'ll set it up for you.',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // No-op.
    }
};
