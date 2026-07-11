<?php

namespace App\WhatsApp\Flow;

/**
 * Holds the set of available flows keyed by id. Populated at boot by
 * WhatsAppServiceProvider as each wave adds flows.
 */
class FlowRegistry
{
    /** @var array<string,FlowInterface> */
    private array $flows = [];

    public function register(FlowInterface $flow): void
    {
        $this->flows[$flow->id()] = $flow;
    }

    public function has(string $id): bool
    {
        return isset($this->flows[$id]);
    }

    public function get(string $id): ?FlowInterface
    {
        return $this->flows[$id] ?? null;
    }

    /** @return array<string,FlowInterface> */
    public function all(): array
    {
        return $this->flows;
    }
}
