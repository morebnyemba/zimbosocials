<?php

namespace App\WhatsApp\Flow;

/**
 * The outcome of a flow step. `step` keeps the conversation inside the flow
 * waiting for more input; `complete`/`fail` end it (the router then shows the
 * menu). `reply` is the text to send back (null = send nothing).
 *
 * A step may additionally carry an interactive payload (reply buttons or a
 * list menu) via withButtons()/withList(). Row/button ids should use the
 * `fs:<value>` convention — the router feeds `<value>` back into the active
 * flow as if the user had typed it, so numbered text input keeps working as
 * the fallback. Global ids (fl_*, cat_*, wa_*) still navigate instead.
 */
class FlowResult
{
    public const STEP = 'step';
    public const COMPLETE = 'complete';
    public const FAIL = 'fail';

    /** Reply buttons: [['id'=>string,'title'=>string],...] (max 3). */
    public ?array $buttons = null;

    /** List spec: ['button'=>string,'sections'=>array,'header'=>?string,'footer'=>?string]. */
    public ?array $list = null;

    private function __construct(
        public string $type,
        public ?string $reply,
        public ?string $nextState = null,
        public array $meta = [],
    ) {}

    /** Attach up to 3 reply buttons to this step. */
    public function withButtons(array $buttons): self
    {
        $this->buttons = $buttons;

        return $this;
    }

    /**
     * Attach an interactive list menu to this step.
     *
     * @param  array  $sections  [['title'=>string,'rows'=>[['id'=>,'title'=>,'description'=>?],...]],...]
     */
    public function withList(string $buttonLabel, array $sections, ?string $header = null, ?string $footer = null): self
    {
        $this->list = ['button' => $buttonLabel, 'sections' => $sections, 'header' => $header, 'footer' => $footer];

        return $this;
    }

    public function hasInteractive(): bool
    {
        return $this->buttons !== null || $this->list !== null;
    }

    /** Stay in the flow, advancing to $nextState and awaiting input. */
    public static function step(?string $reply, string $nextState, array $meta = []): self
    {
        return new self(self::STEP, $reply, $nextState, $meta);
    }

    /**
     * The input wasn't a valid answer for this step. Stays on $state like
     * step(), but marks the result so the router can hand the message to the
     * AI brain first — the flow's error text is only sent if the AI can't help.
     */
    public static function retry(?string $reply, string $state, array $meta = []): self
    {
        return new self(self::STEP, $reply, $state, array_merge($meta, ['unrecognized' => true]));
    }

    public function isRetry(): bool
    {
        return $this->type === self::STEP && ! empty($this->meta['unrecognized']);
    }

    /** End the flow successfully. */
    public static function complete(?string $reply = null, array $meta = []): self
    {
        return new self(self::COMPLETE, $reply, null, $meta);
    }

    /** End the flow with an error message. */
    public static function fail(string $reply, array $meta = []): self
    {
        return new self(self::FAIL, $reply, null, $meta);
    }

    public function isDone(): bool
    {
        return $this->type !== self::STEP;
    }
}
