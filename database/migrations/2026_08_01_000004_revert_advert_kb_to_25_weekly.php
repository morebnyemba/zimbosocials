<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Weekly reverted back to $25 (was briefly $30) — see config/adverts.php.
 * Benefit ladder (video from 3 days, escalating extras) is unchanged.
 */
return new class extends Migration
{
    private const TITLE = 'Sponsored adverts';
    private const MARKER = 'includes an AI video advert';

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
                ."• *\$6* — 1 day (a quick test, we boost a post you have)\n"
                ."• *\$14* — 3 days 🎬 *includes an AI video advert* — most people start here\n"
                ."• *\$25* — 1 week 🎬 video + a progress update partway through\n"
                ."• *\$42* — 2 weeks 🎬 video (pick from 2 concepts) + a progress update\n"
                ."• *\$65* — 1 month 🎬 video (pick from 2 concepts) + priority setup + a progress update + a wrap-up summary\n\n"
                .'Just pick a package and pay — our team then messages you to get your details (what you\'re promoting, your page, the areas to target) and sets it all up.',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // No-op.
    }
};
