<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Console\Command;

/**
 * One rolled-up daily summary for admins — new sign-ups, new orders and deposit
 * proofs still awaiting review over the last 24 hours.
 *
 * This REPLACES the per-event admin_new_registration / admin_new_order WhatsApp
 * pings. Auto-registration fires on essentially every new contact, so those
 * per-event alerts had our own number pushing 20+ near-identical UTILITY
 * messages a day to the admins — a high-volume, low-engagement pattern WhatsApp
 * treats as spam. The events still land in-app; this sends at most one WhatsApp
 * template a day instead.
 *
 * Usage:    php artisan whatsapp:admin-digest [--hours=24]
 * Schedule: daily (see routes/console.php)
 */
class SendAdminDailyDigest extends Command
{
    protected $signature = 'whatsapp:admin-digest {--hours=24 : Look-back window for the counts}';

    protected $description = 'Send admins one rolled-up daily summary (sign-ups, orders, pending proofs)';

    public function handle(): int
    {
        $since = now()->subHours((int) $this->option('hours'));

        $registrations = User::where('created_at', '>=', $since)->count();
        $orders = Order::where('created_at', '>=', $since)->count();

        // Deposit proofs waiting on an admin — money that can't move until
        // someone verifies it, so it's the one count worth chasing.
        $pendingProofs = Transaction::where('type', 'deposit')
            ->where('status', 'pending')
            ->whereNotNull('proof_url')
            ->count();

        // Nothing happened and nothing is waiting — skip the ping entirely.
        if ($registrations === 0 && $orders === 0 && $pendingProofs === 0) {
            $this->info('Nothing to report — no digest sent.');

            return self::SUCCESS;
        }

        $date = now()->format('M j, Y');

        NotificationService::notifyAdmins(
            'admin_daily_digest',
            "Daily summary — {$date}",
            // A short reply keeps the 24-hour service window open so tomorrow's
            // digest (and any deposit-proof alert) is still deliverable.
            "• {$registrations} new sign-ups\n• {$orders} new orders\n• {$pendingProofs} deposit proofs waiting\n\n"
                .'Open the admin panel for details. Reply *OK* to keep alerts flowing.',
            [
                'registrations' => $registrations,
                'orders' => $orders,
                'pending_proofs' => $pendingProofs,
                'date' => $date,
            ],
        );

        $this->info("Digest sent: {$registrations} sign-ups, {$orders} orders, {$pendingProofs} pending proofs.");

        return self::SUCCESS;
    }
}
