<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * Seeds the `manual_deposit_bonus_percent` setting (default 5) so the
 * every-manual-deposit bonus is editable in admin → Settings. Stacks with the
 * referred-user 10% first-deposit welcome bonus (first manual deposit = 15%).
 */
return new class extends Migration
{
    public function up(): void
    {
        Setting::firstOrCreate(
            ['key' => 'manual_deposit_bonus_percent'],
            ['value' => '5', 'group' => 'wallet'],
        );
    }

    public function down(): void
    {
        Setting::where('key', 'manual_deposit_bonus_percent')->delete();
    }
};
