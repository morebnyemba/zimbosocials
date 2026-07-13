<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Meta's create-template API rejects ("Invalid parameter") any template
     * whose BODY ends with a {{n}} variable. Several seeded templates did.
     * Append a short closing line to every stored template still ending with
     * a variable so they can be (re)submitted successfully.
     */
    public function up(): void
    {
        if (! Schema::hasTable('whatsapp_templates')) {
            return;
        }

        foreach (DB::table('whatsapp_templates')->get(['id', 'body']) as $row) {
            $body = rtrim((string) $row->body);
            if (preg_match('/\{\{\d+\}\}\**$/', $body)) {
                DB::table('whatsapp_templates')->where('id', $row->id)->update([
                    'body' => $body."\n\nThank you!",
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Content-only fix; nothing sensible to restore.
    }
};
