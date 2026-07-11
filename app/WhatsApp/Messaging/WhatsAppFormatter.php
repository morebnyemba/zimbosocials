<?php

namespace App\WhatsApp\Messaging;

/**
 * Normalizes AI/markdown text into WhatsApp's own formatting so replies render
 * correctly. WhatsApp uses *bold*, _italic_, ~strike~, ```mono``` — NOT standard
 * markdown — so we fix the common LLM slips (**bold**, ## headers, [text](url)).
 */
class WhatsAppFormatter
{
    public static function clean(string $text): string
    {
        $text = str_replace("\r\n", "\n", trim($text));

        // Strip code fences but keep the inner text (WhatsApp has no fenced blocks).
        $text = preg_replace('/```[a-zA-Z]*\n?/', '', $text);

        // **bold** / __bold__  ->  *bold*
        $text = preg_replace('/\*\*(.+?)\*\*/s', '*$1*', $text);
        $text = preg_replace('/__(.+?)__/s', '*$1*', $text);

        // Markdown headers (#, ##, …) -> bold line.
        $text = preg_replace('/^#{1,6}\s*(.+)$/m', '*$1*', $text);

        // [label](url) -> label (url)   |   bare <url> -> url
        $text = preg_replace('/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/', '$1 ($2)', $text);
        $text = preg_replace('/<(https?:\/\/[^>]+)>/', '$1', $text);

        // Markdown bullets "* item" -> "• item" (bare * means bold in WhatsApp).
        $text = preg_replace('/^\s*\*\s+/m', '• ', $text);
        $text = preg_replace('/^\s*-\s+/m', '• ', $text);

        // Collapse 3+ blank lines to a single blank line.
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }
}
