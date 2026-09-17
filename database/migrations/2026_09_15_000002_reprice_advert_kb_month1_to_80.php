<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * month1 repriced $65 -> $80 (see config/adverts.php) to match the new
 * plans-card graphics. Keeps the seeded "Sponsored adverts" KB entry — which
 * the assistant quotes prices from verbatim — in step with the real price.
 */
return new class extends Migration
{
    private const TITLE = 'Sponsored adverts';

    private const OLD_LINE = '$65* — 1 month';

    private const NEW_LINE = '$80* — 1 month';

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
            'answer' => str_replace(self::OLD_LINE, self::NEW_LINE, $row->answer),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // No-op.
    }
};
