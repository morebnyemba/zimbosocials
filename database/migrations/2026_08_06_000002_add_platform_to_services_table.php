<?php

use App\Models\Service;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Decouples "which icon to show" from "category" (a free-text grouping label
 * an admin can name however they like — "Instagram", "Starter Packs",
 * "Instagram Followers", whatever). 'platform' is a small canonical set
 * (instagram/youtube/tiktok/...) that every reader can use for icon lookup
 * by an exact key instead of fragile substring-matching on category text.
 * Backfilled from each row's current category via the same normalizer
 * already used at upstream-import time (see Service::inferPlatform()), so
 * existing services get a sensible icon immediately.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->string('platform', 30)->nullable()->after('category');
        });

        Service::query()->select('id', 'category')->chunkById(200, function ($services): void {
            foreach ($services as $service) {
                DB::table('services')->where('id', $service->id)
                    ->update(['platform' => Service::inferPlatform((string) $service->category)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn('platform');
        });
    }
};
