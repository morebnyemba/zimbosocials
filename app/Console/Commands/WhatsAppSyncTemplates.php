<?php

namespace App\Console\Commands;

use App\Services\WhatsAppService;
use Illuminate\Console\Command;

class WhatsAppSyncTemplates extends Command
{
    protected $signature = 'whatsapp:sync-templates
                            {--dry-run : Show what would be created without actually creating}
                            {--delete-missing : Delete remote templates not in local config}
                            {--list : List all remote templates and their statuses}';

    protected $description = 'Sync WhatsApp message templates to Meta Business Account';

    public function handle(WhatsAppService $whatsapp): int
    {
        $localTemplates = config('whatsapp-templates.templates', []);
        $language = config('whatsapp-templates.language', 'en');

        // ── List mode ────────────────────────────────────────────────────────
        if ($this->option('list')) {
            return $this->listRemoteTemplates($whatsapp);
        }

        // ── Fetch remote templates ───────────────────────────────────────────
        $this->info('📡 Fetching remote templates...');
        $remote = $whatsapp->listTemplates();

        if (! $remote['ok']) {
            $this->error("Failed to fetch remote templates: {$remote['error']}");
            $this->warn('Tip: Ensure WHATSAPP_API_TOKEN and WHATSAPP_WABA_ID are set in .env');

            return self::FAILURE;
        }

        $remoteByName = collect($remote['templates'])->keyBy('name');
        $this->info("Found {$remoteByName->count()} remote template(s).\n");

        // ── Sync: create missing templates, resubmit rejected ones ──────────
        $created = 0;
        $resubmitted = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($localTemplates as $name => $tpl) {
            $payload = \App\Models\WhatsAppTemplate::metaPayload($name, $tpl, $language);
            $remoteTpl = $remoteByName->get($name);

            if ($remoteTpl !== null) {
                $status = strtoupper($remoteTpl['status'] ?? 'UNKNOWN');

                // A REJECTED/PAUSED template can be edited in place, which
                // resubmits it for review (deleting would block the name 30 days).
                if (in_array($status, ['REJECTED', 'PAUSED'], true) && isset($remoteTpl['id'])) {
                    if ($this->option('dry-run')) {
                        $this->line("  ⏳ <comment>{$name}</comment> — {$status}, would be resubmitted (dry-run)");
                        $resubmitted++;

                        continue;
                    }

                    $this->line("  ↻ Resubmitting <info>{$name}</info> ({$status})...");
                    $result = $whatsapp->updateTemplate((string) $remoteTpl['id'], $payload);

                    if ($result['ok']) {
                        $this->line('    ✓ Resubmitted — pending Meta approval');
                        $resubmitted++;
                    } else {
                        $this->error("    ✗ Failed: {$result['error']}");
                        $failed++;
                    }

                    continue;
                }

                $this->line("  ✓ <info>{$name}</info> — already exists (<comment>{$status}</comment>)");
                $skipped++;

                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("  ⏳ <comment>{$name}</comment> — would be created (dry-run)");
                $created++;

                continue;
            }

            $this->line("  → Creating <info>{$name}</info>...");
            $result = $whatsapp->createTemplate($payload);

            if ($result['ok']) {
                $this->line('    ✓ Created — pending Meta approval');
                $created++;
            } else {
                $this->error("    ✗ Failed: {$result['error']}");
                $failed++;
            }
        }

        // ── Delete orphaned remote templates ─────────────────────────────────
        $deleted = 0;
        if ($this->option('delete-missing')) {
            $localNames = array_keys($localTemplates);
            foreach ($remoteByName as $name => $info) {
                if (! in_array($name, $localNames, true)) {
                    if ($this->option('dry-run')) {
                        $this->line("  🗑 <comment>{$name}</comment> — would be deleted (dry-run)");
                    } else {
                        $this->line("  🗑 Deleting <comment>{$name}</comment>...");
                        $whatsapp->deleteTemplate($name);
                    }
                    $deleted++;
                }
            }
        }

        // ── Summary ──────────────────────────────────────────────────────────
        $this->newLine();
        $this->info('📊 Sync Summary:');
        $this->table(
            ['Action', 'Count'],
            [
                ['Created', $created],
                ['Resubmitted (was rejected)', $resubmitted],
                ['Skipped (exists)', $skipped],
                ['Failed', $failed],
                ['Deleted', $deleted],
            ]
        );

        if ($created > 0 && ! $this->option('dry-run')) {
            $this->warn("\n⚠  New templates require Meta approval before they can be sent.");
            $this->info('   Check status: php artisan whatsapp:sync-templates --list');
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function listRemoteTemplates(WhatsAppService $whatsapp): int
    {
        $result = $whatsapp->listTemplates();

        if (! $result['ok']) {
            $this->error("Failed: {$result['error']}");

            return self::FAILURE;
        }

        $rows = collect($result['templates'])->map(fn ($t) => [
            $t['name'],
            $t['language'] ?? '—',
            $t['status'] ?? '—',
            $t['category'] ?? '—',
            isset($t['id']) ? substr($t['id'], 0, 12).'…' : '—',
        ]);

        $this->table(['Name', 'Language', 'Status', 'Category', 'ID'], $rows->toArray());
        $this->info("Total: {$rows->count()} template(s)");

        return self::SUCCESS;
    }

}
