<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Flags orders that have sat in an active status too long — a deleted
 * provider, a lost upstream order, or a dead sync would otherwise leave them
 * invisible until a customer complains. Alerts admins once per order per week.
 *
 * Usage:   php artisan orders:flag-stuck [--days=5]
 * Schedule: daily (see routes/console.php)
 */
class FlagStuckOrders extends Command
{
    protected $signature = 'orders:flag-stuck {--days=5 : Days an active order may go without any status movement}';

    protected $description = 'Alert admins about orders stuck in an active status with no movement';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $stuck = Order::with('service:id,name')
            ->whereIn('status', ['pending', 'processing', 'in_progress'])
            ->where('updated_at', '<', $cutoff)
            ->orderBy('updated_at')
            ->get();

        if ($stuck->isEmpty()) {
            $this->info('No stuck orders found.');

            return self::SUCCESS;
        }

        $fresh = $stuck->filter(
            // Alert once per order per week, not every day it stays stuck.
            fn (Order $order) => Cache::add("order:stuck_flagged:{$order->id}", true, now()->addWeek())
        );

        $this->warn("{$stuck->count()} stuck order(s); {$fresh->count()} newly flagged.");

        if ($fresh->isEmpty()) {
            return self::SUCCESS;
        }

        $lines = $fresh->take(10)->map(fn (Order $order) => "#{$order->id} ({$order->service?->name}, {$order->status}, no movement since {$order->updated_at->diffForHumans()})"
        )->implode('; ');

        NotificationService::notifyAdmins(
            'admin_stuck_orders',
            "{$fresh->count()} order(s) look stuck",
            "These orders have had no status movement for over {$days} days: {$lines}. Force-sync them or resolve manually.",
            ['order_ids' => $fresh->pluck('id')->values()->all(), 'days' => $days]
        );

        return self::SUCCESS;
    }
}
