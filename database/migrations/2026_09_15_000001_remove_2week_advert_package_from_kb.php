<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The 2-week advert package (week2, $42) was dropped from config/adverts.php
 * — no plans-card image was made for it — so the KB entry the assistant
 * quotes prices from must drop that line too, or it would offer a package
 * the 'advertise' flow can no longer book.
 */
return new class extends Migration
{
    private const TITLE = 'Sponsored adverts';

    private const OLD_LINE = "• *\$42* — 2 weeks 🎬 video (pick from 2 concepts) + a progress update\n";

    public function up(): void
    {
        if (! Schema::hasTable('whatsapp_knowledge_base')) {
            return;
        }

        $row = DB::table('whatsapp_knowledge_base')->where('title', self::TITLE)->first();
        if (! $row || ! is_string($row->answer) || ! str_contains($row->answer, self::OLD_LINE)) {
            return;
        }

        DB::table('whatsapp_knowledge_base')->where('id', $row->id)->update([
            'answer' => str_replace(self::OLD_LINE, '', $row->answer),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // No-op — the package is retired, not temporarily hidden.
    }
};
