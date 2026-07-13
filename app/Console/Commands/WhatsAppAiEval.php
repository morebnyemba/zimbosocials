<?php

namespace App\Console\Commands;

use App\WhatsApp\AI\GeminiProvider;
use Illuminate\Console\Command;

/**
 * Replays the golden set of real user messages against the live Gemini prompt
 * and scores flow-decision accuracy. Run BEFORE deploying any prompt change:
 *
 *   php artisan whatsapp:ai-eval
 *
 * Requires a configured GEMINI key (makes real API calls — one per case).
 */
class WhatsAppAiEval extends Command
{
    protected $signature = 'whatsapp:ai-eval
                            {--set= : Path to a golden-set JSON file (defaults to database/data/whatsapp-ai-golden.json)}
                            {--filter= : Only run cases whose message contains this text}';

    protected $description = 'Score the WhatsApp AI prompt against the golden set of expected flow decisions';

    public function handle(GeminiProvider $ai): int
    {
        if (! $ai->isConfigured()) {
            $this->error('GEMINI key not configured — the eval makes real API calls.');

            return self::FAILURE;
        }

        $path = $this->option('set') ?: base_path('database/data/whatsapp-ai-golden.json');
        $cases = json_decode((string) file_get_contents($path), true);
        if (! is_array($cases)) {
            $this->error("Could not read golden set at {$path}");

            return self::FAILURE;
        }

        if ($filter = $this->option('filter')) {
            $cases = array_values(array_filter($cases, fn ($c) => str_contains($c['message'], $filter)));
        }

        $this->info('Prompt version: '.GeminiProvider::PROMPT_VERSION.' — running '.count($cases).' case(s)...');

        $rows = [];
        $flowHits = 0;
        $entityMisses = 0;

        foreach ($cases as $case) {
            $res = $ai->respond($case['message'], [
                'user' => null,
                'authenticated' => (bool) ($case['authenticated'] ?? true),
                'current_flow' => $case['current_flow'] ?? null,
                'current_state' => $case['current_state'] ?? null,
                'history' => [],
            ]);

            $gotFlow = $res['flow'] ?? null;
            $expectFlow = $case['expect_flow'] ?? null;
            $flowOk = $gotFlow === $expectFlow;
            if ($flowOk) {
                $flowHits++;
            }

            // Entities: every expected key must appear with a loosely-equal value.
            $entityNote = '';
            foreach ((array) ($case['expect_entities'] ?? []) as $key => $expected) {
                $got = $res['flow_data'][$key] ?? null;
                $ok = is_numeric($expected)
                    ? (float) $got === (float) $expected
                    : mb_stripos((string) $got, (string) $expected) !== false;
                if (! $ok) {
                    $entityMisses++;
                    $entityNote .= "{$key}≠".json_encode($got).' ';
                }
            }

            $rows[] = [
                mb_substr($case['message'], 0, 42),
                $expectFlow ?? '—',
                $gotFlow ?? '—',
                $flowOk ? '✓' : '✗',
                $entityNote !== '' ? trim($entityNote) : ($case['expect_entities'] ?? null ? '✓' : '—'),
            ];
        }

        $this->table(['Message', 'Expected flow', 'Got', 'Flow', 'Entities'], $rows);

        $total = count($cases);
        $pct = $total > 0 ? round(($flowHits / $total) * 100) : 0;
        $this->info("Flow accuracy: {$flowHits}/{$total} ({$pct}%) · entity misses: {$entityMisses}");

        return $flowHits === $total && $entityMisses === 0 ? self::SUCCESS : self::FAILURE;
    }
}
