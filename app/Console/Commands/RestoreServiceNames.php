<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Service;
use Illuminate\Console\Command;

/**
 * Undo AI name enhancement. Every enhancement records the previous name in the
 * audit trail, so the original provider names can be put back — needed when an
 * enhancement run over-simplified a whole group of services down to the same
 * generic title ("Facebook Followers" x6) and lost what told them apart.
 *
 * Restores the OLDEST recorded name per service (the true pre-AI original),
 * so it's still correct after several enhancement runs.
 */
class RestoreServiceNames extends Command
{
    protected $signature = 'services:restore-names {--dry-run : Show what would change without saving}
                                                   {--only-duplicates : Restore only services whose current name is shared with another service}';

    protected $description = 'Restore service names replaced by AI enhancement (from the audit trail)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $byService = AuditLog::query()
            ->where('action', 'service.ai_enhanced')
            ->where('model_type', Service::class)
            ->orderBy('id') // oldest first — the first entry holds the true original
            ->get(['model_id', 'old_values'])
            ->groupBy('model_id');

        if ($byService->isEmpty()) {
            $this->info('No AI name enhancements found in the audit trail — nothing to restore.');

            return self::SUCCESS;
        }

        // Names currently shared by more than one service — the damage signature.
        $duplicated = Service::query()
            ->selectRaw('name, COUNT(*) as c')
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('name')
            ->map(fn ($n) => mb_strtolower((string) $n))
            ->flip();

        $onlyDuplicates = (bool) $this->option('only-duplicates');
        $restored = 0;
        $skipped = 0;

        foreach ($byService as $serviceId => $logs) {
            $original = $logs->first()->old_values['name'] ?? null;
            $service = Service::find($serviceId);

            if (! $service || ! is_string($original) || $original === '' || $original === $service->name) {
                $skipped++;

                continue;
            }

            if ($onlyDuplicates && ! $duplicated->has(mb_strtolower((string) $service->name))) {
                $skipped++;

                continue;
            }

            $this->line("#{$service->id}  \"{$service->name}\"  →  \"{$original}\"");

            if (! $dryRun) {
                $service->update(['name' => $original]);
            }

            $restored++;
        }

        $verb = $dryRun ? 'Would restore' : 'Restored';
        $this->info("{$verb} {$restored} service name(s); skipped {$skipped}.");

        if ($dryRun) {
            $this->comment('Dry run — nothing was saved. Re-run without --dry-run to apply.');
        }

        return self::SUCCESS;
    }
}
