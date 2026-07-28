<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The services table shipped with no indexes beyond its primary key, which was
 * survivable at a few dozen rows and stops being survivable once the upstream
 * catalogue is synced in.
 *
 * Every admin services page load ran, against the full table: a filesort for
 * "ORDER BY category, display_order", a DISTINCT category scan, a GROUP BY
 * category scan, a GROUP BY is_active scan, and the pagination COUNT(*). That
 * is several full scans per request, which is why the page failed only
 * sometimes — it depended on cache warmth and what else was running.
 *
 * Searching was worse: it also probes service_upstreams.external_service_id,
 * which had no index of its own either.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            // Serves both the default ordering and the category filter.
            $table->index(['category', 'display_order'], 'services_category_order_idx');
            // The active/inactive filter and its GROUP BY count.
            $table->index('is_active', 'services_is_active_idx');
        });

        Schema::table('service_upstreams', function (Blueprint $table): void {
            // Admins paste a provider's service id into search; this is the
            // column that lookup lands on.
            $table->index('external_service_id', 'service_upstreams_external_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropIndex('services_category_order_idx');
            $table->dropIndex('services_is_active_idx');
        });

        Schema::table('service_upstreams', function (Blueprint $table): void {
            $table->dropIndex('service_upstreams_external_id_idx');
        });
    }
};
