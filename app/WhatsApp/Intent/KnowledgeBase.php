<?php

namespace App\WhatsApp\Intent;

use Illuminate\Support\Facades\DB;

/**
 * FAQ retrieval used ONLY to ground Gemini — the top matching entries are
 * injected into the AI prompt as context, never returned to the user directly.
 * Uses a portable keyword-overlap scan (works on MySQL and the sqlite test DB).
 */
class KnowledgeBase
{
    /**
     * Return up to $limit relevant entries for grounding the AI.
     *
     * @return array<int, array{title:string, answer:string}>
     */
    public function search(string $text, int $limit = 3): array
    {
        $needle = mb_strtolower(trim($text));
        if (mb_strlen($needle) < 3) {
            return [];
        }

        $rows = DB::table('whatsapp_knowledge_base')
            ->where('status', true)
            ->get(['id', 'title', 'question', 'answer', 'keywords']);

        $scored = [];
        foreach ($rows as $row) {
            $score = $this->score($needle, $row);
            if ($score >= 1) {
                $scored[] = ['score' => $score, 'id' => $row->id, 'title' => $row->title, 'answer' => $row->answer];
            }
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);
        $top = array_slice($scored, 0, $limit);

        if ($top) {
            DB::table('whatsapp_knowledge_base')->whereIn('id', array_column($top, 'id'))->increment('hits');
        }

        return array_map(fn ($e) => ['title' => $e['title'], 'answer' => $e['answer']], $top);
    }

    private function score(string $needle, object $row): int
    {
        $score = 0;
        $words = array_filter(explode(' ', preg_replace('/[^a-z0-9 ]/', ' ', $needle)));
        $haystack = mb_strtolower(($row->keywords ?? '').' '.$row->question.' '.$row->title);

        foreach ($words as $w) {
            if (mb_strlen($w) >= 3 && str_contains($haystack, $w)) {
                $score++;
            }
        }

        return $score;
    }
}
