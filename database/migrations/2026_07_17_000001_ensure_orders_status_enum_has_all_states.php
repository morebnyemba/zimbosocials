<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Guarantee the orders.status enum contains every state the app writes —
 * above all 'pending', the state a new order is created in. If a production
 * database first migrated with an older/narrower enum, inserting 'pending'
 * (or 'processing'/'in_progress') fails under MySQL strict mode and 500s the
 * order right after placement. Re-declaring the full set is idempotent: on a
 * database that already has it, this is a no-op. (SQLite has no ENUM type and
 * stores it as text, so it needs — and gets — nothing here.)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders') || DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            "ALTER TABLE orders MODIFY status ENUM("
            ."'pending','processing','in_progress','completed','partial','cancelled','refunded'"
            .") NOT NULL DEFAULT 'pending'"
        );
    }

    public function down(): void
    {
        // No-op: narrowing the enum could truncate valid rows. Intentionally
        // left as-is (the set is a superset of any earlier definition).
    }
};
