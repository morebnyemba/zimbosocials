<?php

namespace App\WhatsApp\Flow;

/**
 * The outcome of a flow step. `step` keeps the conversation inside the flow
 * waiting for more input; `complete`/`fail` end it (the router then shows the
 * menu). `reply` is the text to send back (null = send nothing).
 */
class FlowResult
{
    public const STEP = 'step';
    public const COMPLETE = 'complete';
    public const FAIL = 'fail';

    private function __construct(
        public string $type,
        public ?string $reply,
        public ?string $nextState = null,
        public array $meta = [],
    ) {}

    /** Stay in the flow, advancing to $nextState and awaiting input. */
    public static function step(?string $reply, string $nextState, array $meta = []): self
    {
        return new self(self::STEP, $reply, $nextState, $meta);
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

    /** Whether the router should render the main menu after this result. */
    public function showMenuAfter(): bool
    {
        return $this->isDone() && empty($this->meta['skipMenu']);
    }
}
