<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * Ship admin 2FA disabled by default. The emailed second factor depends on
 * working SMTP — with the stock MAIL_MAILER=log it locked admins out of a
 * fresh deployment entirely. Verify mail with the Settings "Send Test Email"
 * button first, then enable the toggle (Settings → Platform Settings) or run
 * `php artisan admin:2fa on`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Setting::set('admin_2fa_enabled', '0', 'security');
    }

    public function down(): void
    {
        Setting::set('admin_2fa_enabled', '1', 'security');
    }
};
