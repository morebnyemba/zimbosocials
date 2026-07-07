<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * Seeds the `admin_2fa_enabled` toggle (default on). Admin logins require an
 * emailed 6-digit code on top of the password; set to 0 to disable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Setting::firstOrCreate(
            ['key' => 'admin_2fa_enabled'],
            ['value' => '1', 'group' => 'security'],
        );
    }

    public function down(): void
    {
        Setting::where('key', 'admin_2fa_enabled')->delete();
    }
};
