<?php

namespace App\WhatsApp\Flow;

use App\Models\User;
use App\WhatsApp\Messaging\ResponseBuilder;
use App\WhatsApp\Session\SessionContext;

/**
 * Base class for flows. Provides the resolved User for the current context and
 * shared formatting helpers. Subclasses implement prompt()/handle().
 */
abstract class AbstractFlow implements FlowInterface
{
    public function __construct(protected ResponseBuilder $rb) {}

    public function entryState(): string
    {
        return 'start';
    }

    public function authRequired(): bool
    {
        return true;
    }

    /** The authenticated app user for this conversation, if any. */
    protected function user(SessionContext $ctx): ?User
    {
        $id = $ctx->get('_user_id');

        return $id ? User::find($id) : null;
    }

    protected function money(float $amount, string $currency = 'USD'): string
    {
        return number_format($amount, 2).' '.$currency;
    }
}
