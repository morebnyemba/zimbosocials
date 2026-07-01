<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Referral commission activity window: ongoing commissions pause after a period
 * with no new referral. Adds a per-user "warned at" timestamp (so we warn once
 * per window, not daily) and seeds the window + warning-lead settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('referral_commission_warned_at')->nullable()->after('referred_bonus_awarded_at');
        });

        Setting::firstOrCreate(['key' => 'referral_commission_active_days'], ['value' => '60', 'group' => 'referral']);
        Setting::firstOrCreate(['key' => 'referral_commission_warn_days'], ['value' => '7', 'group' => 'referral']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('referral_commission_warned_at');
        });

        Setting::whereIn('key', ['referral_commission_active_days', 'referral_commission_warn_days'])->delete();
    }
};
