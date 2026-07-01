<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * Seeds `referral_min_qualifying_deposit` (default 5) — the minimum first
 * deposit required for referral rewards to apply, so the flat referrer reward
 * can't be farmed with tiny ($1) deposits.
 */
return new class extends Migration
{
    public function up(): void
    {
        Setting::firstOrCreate(
            ['key' => 'referral_min_qualifying_deposit'],
            ['value' => '5', 'group' => 'referral'],
        );
    }

    public function down(): void
    {
        Setting::where('key', 'referral_min_qualifying_deposit')->delete();
    }
};
