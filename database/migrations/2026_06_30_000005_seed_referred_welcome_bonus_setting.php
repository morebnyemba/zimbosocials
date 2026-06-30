<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * Seeds the `referred_first_deposit_bonus_percent` setting (default 10) so the
 * referred-user first-deposit welcome bonus is editable in admin → Settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Setting::firstOrCreate(
            ['key' => 'referred_first_deposit_bonus_percent'],
            ['value' => '10', 'group' => 'referral'],
        );
    }

    public function down(): void
    {
        Setting::where('key', 'referred_first_deposit_bonus_percent')->delete();
    }
};
