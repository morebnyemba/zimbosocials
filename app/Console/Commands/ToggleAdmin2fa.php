<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;

/**
 * Emergency switch for the admin email-code second factor. If outbound mail
 * breaks, admins cannot receive login codes — this is the SSH/cron-accessible
 * way back in:  php artisan admin:2fa off
 */
class ToggleAdmin2fa extends Command
{
    protected $signature = 'admin:2fa {state : on or off}';

    protected $description = 'Enable or disable the emailed second factor for admin logins';

    public function handle(): int
    {
        $state = strtolower((string) $this->argument('state'));

        if (! in_array($state, ['on', 'off'], true)) {
            $this->error("State must be 'on' or 'off'.");

            return self::FAILURE;
        }

        Setting::set('admin_2fa_enabled', $state === 'on' ? '1' : '0', 'security');

        $this->info('Admin 2FA is now '.strtoupper($state).'.');

        return self::SUCCESS;
    }
}
