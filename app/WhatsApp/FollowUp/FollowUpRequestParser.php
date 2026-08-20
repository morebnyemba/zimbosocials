<?php

namespace App\WhatsApp\FollowUp;

use Illuminate\Support\Carbon;

/**
 * Recognises a customer telling the bot when THEY want to be followed up
 * with — "remind me tomorrow", "check back in a week", "stop following up
 * for now" — so the automated systems can honour a stated preference instead
 * of guessing a fixed cadence.
 *
 * Deliberately narrow: a follow-up REFERENCE (remind / follow up / check
 * back / ...) must be present, so an unrelated "I'll pay tomorrow" is never
 * mistaken for a scheduling request. A recognised time expression wins over
 * a bare "stop"/"don't" — "don't forget to follow up in 3 days" schedules 3
 * days out rather than stopping — and only when no time expression is found
 * does the stop wording fall back to an open-ended pause.
 */
class FollowUpRequestParser
{
    /** @return array{until: Carbon, label: string}|null */
    public function parse(string $text, ?Carbon $now = null): ?array
    {
        $now ??= Carbon::now();
        $t = mb_strtolower(trim($text));

        if ($t === '' || ! preg_match('/\b(remind|follow(?:ing)?[\s-]?up|check(ing)?\s*back|reach\s*out|contact\s+me|message\s+me|text\s+me)\b/u', $t)) {
            return null;
        }

        if (preg_match('/\bin\s+(\d+)\s*week/u', $t, $m)) {
            $n = max(1, (int) $m[1]);

            return ['until' => $now->copy()->addWeeks($n), 'label' => "in {$n} week".($n > 1 ? 's' : '')];
        }

        if (preg_match('/\bnext\s+week\b|\bin\s+a\s+week\b|\ba\s+week\b/u', $t)) {
            return ['until' => $now->copy()->addWeek(), 'label' => 'in a week'];
        }

        if (preg_match('/\bnext\s+month\b|\ba\s+month\b|\bin\s+a\s+month\b/u', $t)) {
            return ['until' => $now->copy()->addMonth(), 'label' => 'in a month'];
        }

        if (preg_match('/\bin\s+(\d+)\s*day/u', $t, $m)) {
            $n = max(1, (int) $m[1]);

            return ['until' => $now->copy()->addDays($n), 'label' => "in {$n} day".($n > 1 ? 's' : '')];
        }

        if (preg_match('/\btomorrow\b/u', $t)) {
            return ['until' => $now->copy()->addDay(), 'label' => 'tomorrow'];
        }

        if (preg_match('/\b(stop|don\'?t|no more|never\s*mind)\b/u', $t)) {
            return ['until' => $now->copy()->addDays(180), 'label' => 'for now — just message me any time you want to pick things back up'];
        }

        return null;
    }
}
