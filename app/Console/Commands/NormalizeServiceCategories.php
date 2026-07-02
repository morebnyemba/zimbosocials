<?php

namespace App\Console\Commands;

use App\Models\Service;
use App\Support\ServiceCategoryNormalizer;
use Illuminate\Console\Command;

/**
 * One-time (repeatable, idempotent) cleanup for services imported before
 * category normalization existed at import time. Categories were stored
 * verbatim from whatever string each upstream provider sent, fragmenting a
 * single platform (e.g. Instagram) into many near-duplicate category tabs
 * across the customer catalog, order form, and marketing page.
 *
 * Safe to re-run any time — services already on a canonical category are
 * left untouched.
 */
class NormalizeServiceCategories extends Command
{
    protected $signature = 'services:normalize-categories {--dry-run : Show the planned changes without applying them}';

    protected $description = 'Normalize service categories to canonical platform names (e.g. "Instagram Followers", "INSTAGRAM [Real]" -> "Instagram").';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $rawCategories = Service::query()->distinct()->pluck('category');

        $changes = [];
        foreach ($rawCategories as $raw) {
            $normalized = ServiceCategoryNormalizer::normalize((string) $raw);
            if ($normalized !== $raw) {
                $changes[(string) $raw] = $normalized;
            }
        }

        if (empty($changes)) {
            $this->info('All service categories are already normalized. Nothing to do.');

            return self::SUCCESS;
        }

        $this->table(
            ['Current category', 'Normalizes to', 'Services affected'],
            collect($changes)->map(fn ($to, $from) => [
                $from,
                $to,
                Service::where('category', $from)->count(),
            ])->values()->all()
        );

        if ($dryRun) {
            $this->comment('Dry run — no changes applied. Re-run without --dry-run to apply.');

            return self::SUCCESS;
        }

        $totalUpdated = 0;
        foreach ($changes as $from => $to) {
            $totalUpdated += Service::where('category', $from)->update(['category' => $to]);
        }

        $this->info("Normalized {$totalUpdated} service(s) across ".count($changes).' distinct categories.');

        return self::SUCCESS;
    }
}
