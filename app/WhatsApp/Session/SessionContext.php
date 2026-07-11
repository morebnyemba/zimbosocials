<?php

namespace App\WhatsApp\Session;

/**
 * In-memory working copy of a conversation's state for one request. Hydrated
 * from / persisted to a whatsapp_sessions row by SessionManager.
 *
 * `context` is a free-form bag flows use to stash step data. Keys prefixed with
 * '_' are engine/identity scoped (e.g. _user_id) and survive resetFlow().
 */
class SessionContext
{
    public ?string $flow = null;
    public ?string $state = null;
    public array $stateStack = [];
    public array $context = [];
    public bool $wasExpired = false;

    public function __construct(public string $phone) {}

    public function inFlow(): bool
    {
        return $this->flow !== null;
    }

    public function setFlow(string $flow, ?string $state = null): void
    {
        $this->flow = $flow;
        $this->state = $state;
        $this->stateStack = [];
    }

    public function setState(?string $state): void
    {
        $this->state = $state;
    }

    public function pushState(string $state): void
    {
        if ($this->state !== null) {
            $this->stateStack[] = $this->state;
        }
        $this->state = $state;
    }

    public function popState(): ?string
    {
        $this->state = array_pop($this->stateStack);

        return $this->state;
    }

    /** Clear the active flow, keeping identity-scoped ('_') context keys. */
    public function resetFlow(): void
    {
        $this->flow = null;
        $this->state = null;
        $this->stateStack = [];
        $this->context = array_filter(
            $this->context,
            fn ($k) => is_string($k) && str_starts_with($k, '_'),
            ARRAY_FILTER_USE_KEY
        );
    }

    public function get(string $key, $default = null)
    {
        return $this->context[$key] ?? $default;
    }

    public function set(string $key, $value): void
    {
        $this->context[$key] = $value;
    }

    public function forget(string $key): void
    {
        unset($this->context[$key]);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->context);
    }

    /** Pull a one-time prefill value (set by the AI layer) for a flow field. */
    public function pullPrefill(string $field, $default = null)
    {
        $key = '_prefill_'.$field;
        if (! $this->has($key)) {
            return $default;
        }
        $value = $this->context[$key];
        $this->forget($key);

        return $value;
    }
}
