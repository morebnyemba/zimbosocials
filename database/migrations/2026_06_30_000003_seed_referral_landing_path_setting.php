<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * Seeds the `referral_landing_path` setting so it shows up (and is editable) on
 * the admin Settings page. Defaults to the home page. firstOrCreate keeps it
 * idempotent and never clobbers an admin-chosen value.
 */
return new class extends Migration
{
    public function up(): void
    {
        Setting::firstOrCreate(
            ['key' => 'referral_landing_path'],
            ['value' => '/', 'group' => 'referral'],
        );
    }

    public function down(): void
    {
        Setting::where('key', 'referral_landing_path')->delete();
    }
};
