<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Heal WhatsApp templates that Meta rejects on sync:
     *  - "Leading or trailing params not allowed" — body starts/ends with {{n}}.
     *  - "Parameters words ratio exceeds limit" — too many variables for the
     *    body length.
     *
     * The DB rows override config at boot, and the original create-migration
     * used insertOrIgnore (never updates existing rows), so fixing config alone
     * doesn't reach a populated DB. This reads the CORRECTED config file
     * directly (bypassing the DB override) and rewrites only the rows that still
     * violate — valid admin-customised templates are left untouched.
     */
    public function up(): void
    {
        if (! Schema::hasTable('whatsapp_templates')) {
            return;
        }

        // Pristine config, straight from the file — not the DB-overridden config().
        $pristine = require config_path('whatsapp-templates.php');
        $defaults = $pristine['templates'] ?? [];

        foreach (DB::table('whatsapp_templates')->get(['id', 'name', 'body']) as $row) {
            if (! $this->violatesMeta((string) $row->body)) {
                continue; // already compliant (incl. valid custom edits)
            }

            $fix = $defaults[$row->name] ?? null;
            if ($fix === null || $this->violatesMeta((string) $fix['body'])) {
                continue; // no compliant replacement available
            }

            DB::table('whatsapp_templates')->where('id', $row->id)->update([
                'body' => $fix['body'],
                'params' => json_encode($fix['params'] ?? []),
                'updated_at' => now(),
            ]);
        }

        Cache::forget('wa:templates:config');
    }

    /** Meta rejects a body that leads/trails with a variable or is variable-heavy. */
    private function violatesMeta(string $body): bool
    {
        $body = trim($body);

        if (preg_match('/^[\s*_~]*\{\{\d+\}\}/u', $body) || preg_match('/\{\{\d+\}\}[\s*_~]*$/u', $body)) {
            return true;
        }

        preg_match_all('/\{\{\d+\}\}/', $body, $m);
        $vars = count(array_unique($m[0]));
        if ($vars === 0) {
            return false;
        }

        $stripped = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', preg_replace('/\{\{\d+\}\}/', '', $body));
        $words = count(array_filter(preg_split('/\s+/', trim($stripped))));

        // Keep variables comfortably under a quarter of the total tokens.
        return $vars / ($vars + max($words, 1)) > 0.25;
    }

    public function down(): void
    {
        // Content-only fix; nothing sensible to restore.
    }
};
