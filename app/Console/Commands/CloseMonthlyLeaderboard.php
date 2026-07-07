<?php

namespace App\Console\Commands;

use App\Models\LeaderboardSnapshot;
use App\Models\Transaction;
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

    public function handle(LeaderboardService $leaderboardService): int
    {
        $previousMonth = Carbon::now()->subMonth();
        $year = (int) ($this->option('year') ?? $previousMonth->year);
        $month = (int) ($this->option('month') ?? $previousMonth->month);

        $monthLabel = Carbon::createFromDate($year, $month, 1)->format('F Y');

        $this->info("Closing leaderboard for {$monthLabel}...");

        $created = $leaderboardService->snapshotMonth($year, $month);

        if ($created === 0) {
            // Don't bail out here: a previous run may have crashed between
            // snapshotting and awarding, leaving is_awarded=false rows behind.
            // Continue so those winners still get their prizes.
            $this->warn('No new snapshot entries created (month already closed or no qualifying participants). Checking for unawarded prizes...');
        } else {
            $this->info("Created {$created} snapshot entries.");
        }

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

            // Credit wallet bonus if prize has one — idempotent via the
            // transaction reference so a crashed/re-run close never double-pays.
            $reference = "LB-{$year}-{$month}-{$snapshot->category}-R{$snapshot->rank}";

            if ($prize->bonus_amount > 0) {
                $alreadyCredited = Transaction::query()
                    ->where('user_id', $user->id)
                    ->where('type', 'bonus')
                    ->where('reference', $reference)
                    ->exists();

                if (! $alreadyCredited) {
                    $user->creditBalance(
                        $prize->bonus_amount,
                        'leaderboard_prize',
                        $reference,
                        'bonus'
                    );
                }
            }

            $snapshot->update([
                'is_awarded' => true,
                'awarded_at' => now(),
                'notes' => $prize->bonus_amount > 0
                    ? "Auto-credited \${$prize->bonus_amount} wallet bonus."
                    : 'Prize requires manual fulfillment.',
            ]);

            $awarded++;

            // Notify the winner. This used to pass (User, message, type) into
            // notify(int $userId, string $type, string $title, string $body),
            // which fatally crashed the scheduled --notify run after the first
            // award and stranded every remaining winner.
            if ($this->option('notify')) {
                $categoryLabel = ucfirst($snapshot->category);
                $ordinal = $this->ordinal($snapshot->rank);

                try {
                    NotificationService::notify(
                        (int) $user->id,
                        'leaderboard_prize',
                        'Leaderboard Prize',
                        "🏆 Congratulations! You finished {$ordinal} on the {$categoryLabel} Leaderboard for {$monthLabel}! Your prize: {$prize->title}.",
                        ['year' => $year, 'month' => $month, 'rank' => $snapshot->rank, 'prize' => $prize->title]
                    );
                } catch (\Throwable $e) {
                    // A notification failure must never stop the award loop.
                    $this->warn("Failed to notify user #{$user->id}: {$e->getMessage()}");
                }
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
