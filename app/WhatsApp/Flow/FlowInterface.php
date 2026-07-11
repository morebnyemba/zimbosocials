<?php

namespace App\WhatsApp\Flow;

use App\WhatsApp\Session\SessionContext;

interface FlowInterface
{
    /** Stable flow id (e.g. 'balance', 'track'). */
    public function id(): string;

    /** State the flow starts in. */
    public function entryState(): string;

    /** Whether the user must be authenticated to run this flow. */
    public function authRequired(): bool;

    /** Render the prompt for a state (called on entry / resume). */
    public function prompt(string $state, SessionContext $ctx): FlowResult;

    /** Process user input at the current state. */
    public function handle(string $state, string $input, SessionContext $ctx): FlowResult;
}
