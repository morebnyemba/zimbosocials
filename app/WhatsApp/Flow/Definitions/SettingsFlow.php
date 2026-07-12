<?php

namespace App\WhatsApp\Flow\Definitions;

use App\Models\WhatsAppAccount;
use App\WhatsApp\Flow\AbstractFlow;
use App\WhatsApp\Flow\FlowResult;
use App\WhatsApp\Session\SessionContext;

/**
 * View and toggle assistant settings. Flow id: 'settings'.
 * States: menu (show) → toggle notifications.
 */
class SettingsFlow extends AbstractFlow
{
    public function id(): string
    {
        return 'settings';
    }

    public function prompt(string $state, SessionContext $ctx): FlowResult
    {
        $account = WhatsAppAccount::where('wa_phone', $ctx->phone)->first();
        $notifs = $account && $account->opted_in ? 'On ✅' : 'Off 🔕';

        $on = $account && $account->opted_in;

        return FlowResult::step("⚙️ *Settings*\n\nNotifications: {$notifs}", 'toggle')->withButtons([
            ['id' => 'fs:1', 'title' => $on ? '🔕 Turn off' : '🔔 Turn on'],
            ['id' => 'fs:back', 'title' => '↩ Back'],
        ]);
    }

    public function handle(string $state, string $input, SessionContext $ctx): FlowResult
    {
        if (trim($input) !== '1') {
            return FlowResult::complete('No changes made. Type *menu* to go back.');
        }

        $account = WhatsAppAccount::where('wa_phone', $ctx->phone)->first();
        if (! $account) {
            return FlowResult::fail('Please try again from the *menu*.');
        }

        $account->update(['opted_in' => ! $account->opted_in]);

        return FlowResult::complete(
            $account->opted_in
                ? "🔔 Notifications turned *on*."
                : "🔕 Notifications turned *off*. You'll still get replies when you message us."
        );
    }
}
