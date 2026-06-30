<?php

namespace App\Console\Commands;

use App\Models\LeaderboardSnapshot;
use App\Models\User;
use App\Services\LeaderboardService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CloseMonthlyLeaderboard extends Command
{
    protected $signature = 'leaderboard:close-month
                            {--year= : The year to close (defaults to previous month)}
                            {--month= : The month to close (defaults to previous month)}
                            {--notify : Send notifications to winners}';

    protected $description = 'Snapshot final leaderboard rankings for the previous month and associate prizes.';

    public function handle(LeaderboardService $leaderboardService, NotificationService $notificationService): int
    {
        $previousMonth = Carbon::now()->subMonth();
        $year = (int) ($this->option('year') ?? $previousMonth->year);
        $month = (int) ($this->option('month') ?? $previousMonth->month);

        $monthLabel = Carbon::createFromDate($year, $month, 1)->format('F Y');

        $this->info("Closing leaderboard for {$monthLabel}...");

        $created = $leaderboardService->snapshotMonth($year, $month);

        if ($created === 0) {
            $this->warn('No new snapshot entries created. The month may have already been closed or there were no qualifying participants.');

            return self::SUCCESS;
        }

        $this->info("Created {$created} snapshot entries.");

        // Credit wallet bonuses for prizes that have a bonus_amount
        $snapshots = LeaderboardSnapshot::with(['user', 'prize'])
            ->where('year', $year)
            ->where('month', $month)
            ->whereNotNull('prize_id')
            ->where('is_awarded', false)
            ->get();

        $awarded = 0;

        foreach ($snapshots as $snapshot) {
            $prize = $snapshot->prize;
            $user = $snapshot->user;

            if (! $prize || ! $user) {
                continue;
            }

            // Credit wallet bonus if prize has one
            if ($prize->bonus_amount > 0) {
                $user->creditBalance(
                    $prize->bonus_amount,
                    'leaderboard_prize',
                    "LB-{$year}-{$month}-{$snapshot->category}-R{$snapshot->rank}",
                    'bonus'
                );
            }

            $snapshot->update([
                'is_awarded' => true,
                'awarded_at' => now(),
                'notes' => $prize->bonus_amount > 0
                    ? "Auto-credited \${$prize->bonus_amount} wallet bonus."
                    : 'Prize requires manual fulfillment.',
            ]);

            $awarded++;

            // Notify the winner
            if ($this->option('notify') && $user) {
                $categoryLabel = ucfirst($snapshot->category);
                $ordinal = $this->ordinal($snapshot->rank);

                $notificationService->notify(
                    $user,
                    "🏆 Congratulations! You finished {$ordinal} on the {$categoryLabel} Leaderboard for {$monthLabel}! Your prize: {$prize->title}.",
                    'leaderboard_prize'
                );
            }
        }

        $this->info("Awarded {$awarded} prizes.");

        return self::SUCCESS;
    }

    private function ordinal(int $number): string
    {
        $suffixes = ['th', 'st', 'nd', 'rd'];
        $mod = $number % 100;

        return $number . ($suffixes[($mod - 20) % 10] ?? $suffixes[$mod] ?? $suffixes[0]);
    }
}
