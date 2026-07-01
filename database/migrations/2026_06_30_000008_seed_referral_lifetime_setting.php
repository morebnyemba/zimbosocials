<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * Seeds `referral_lifetime_months` (default 36) — months after a referred user
 * joins before that referral permanently stops earning commissions. 0 disables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Setting::firstOrCreate(
            ['key' => 'referral_lifetime_months'],
            ['value' => '36', 'group' => 'referral'],
        );
    }

    public function down(): void
    {
        Setting::where('key', 'referral_lifetime_months')->delete();
    }
};
