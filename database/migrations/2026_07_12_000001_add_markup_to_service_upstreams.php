<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Persist the per-service markup on the upstream pivot so the nightly
     * `upstream:sync-services` job can reproduce the operator's chosen margin
     * instead of resetting every price to a hardcoded 20%.
     */
    public function up(): void
    {
        Schema::table('service_upstreams', function (Blueprint $table) {
            $table->string('markup_type')->default('percentage')->after('external_rate'); // 'percentage' | 'fixed'
            $table->decimal('markup_value', 10, 4)->default(20)->after('markup_type');
        });

        // Backfill: recover the markup already baked into each service's live rate
        // so the first sync after this migration does not change existing prices.
        // For the primary upstream this is exact (service.rate was derived from it);
        // for fallbacks it is a reasonable estimate off the same rate.
        $pivots = DB::table('service_upstreams')
            ->join('services', 'services.id', '=', 'service_upstreams.service_id')
            ->where('service_upstreams.external_rate', '>', 0)
            ->where('services.rate', '>', 0)
            ->select(
                'service_upstreams.id',
                'service_upstreams.external_rate',
                'services.rate as local_rate'
            )
            ->get();

        foreach ($pivots as $pivot) {
            $markup = round((((float) $pivot->local_rate / (float) $pivot->external_rate) - 1) * 100, 4);

            DB::table('service_upstreams')
                ->where('id', $pivot->id)
                ->update([
                    'markup_type' => 'percentage',
                    'markup_value' => max($markup, 0),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('service_upstreams', function (Blueprint $table) {
            $table->dropColumn(['markup_type', 'markup_value']);
        });
    }
};
