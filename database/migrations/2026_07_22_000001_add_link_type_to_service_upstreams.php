<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What LINK FORMAT a given upstream expects for a service. The SMM provider API
 * never states this — it lives in the service name/description — so it's stored
 * per mapping (a service can fail over to providers that want different formats)
 * and used to transform the customer's pasted link at dispatch time.
 *
 *   url      — full URL, sent as-is (the default; today's behaviour)
 *   username — bare handle, derived from the URL (e.g. tiktok.com/@jane -> jane)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_upstreams', function (Blueprint $table): void {
            $table->string('link_type', 20)->default('url')->after('external_service_id');
        });
    }

    public function down(): void
    {
        Schema::table('service_upstreams', function (Blueprint $table): void {
            $table->dropColumn('link_type');
        });
    }
};
