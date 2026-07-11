<?php

namespace App\WhatsApp\Intent;

use Illuminate\Support\Facades\DB;

/**
 * Deterministic FAQ lookup consulted before spending an AI call. Matches the
 * user's text against whatsapp_knowledge_base keywords/question with a simple
 * portable LIKE scan (works on both MySQL and the sqlite test DB).
 */
class KnowledgeBase
{
    /** @return array{title:string, answer:string}|null */
    public function lookup(string $text): ?array
    {
        $needle = mb_strtolower(trim($text));
        if (mb_strlen($needle) < 3) {
            return null;
        }

        $rows = DB::table('whatsapp_knowledge_base')
            ->where('status', true)
            ->get(['id', 'title', 'question', 'answer', 'keywords']);

        if ($rows->isEmpty()) {
            return null;
        }

        $best = null;
        $bestScore = 0;
        foreach ($rows as $row) {
            $score = $this->score($needle, $row);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $row;
            }
        }

        if (! $best || $bestScore < 2) {
            return null;
        }

        DB::table('whatsapp_knowledge_base')->where('id', $best->id)->increment('hits');

        return ['title' => $best->title, 'answer' => $best->answer];
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
