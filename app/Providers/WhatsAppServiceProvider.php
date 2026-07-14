<?php

namespace App\Providers;

use App\WhatsApp\Flow\Definitions\AskAiFlow;
use App\WhatsApp\Flow\Definitions\BrowseServicesFlow;
use App\WhatsApp\Flow\Definitions\CreateOrderFlow;
use App\WhatsApp\Flow\Definitions\CreateTicketFlow;
use App\WhatsApp\Flow\Definitions\DepositFundsFlow;
use App\WhatsApp\Flow\Definitions\FaqFlow;
use App\WhatsApp\Flow\Definitions\ForgotPasswordFlow;
use App\WhatsApp\Flow\Definitions\LinkAccountFlow;
use App\WhatsApp\Flow\Definitions\MyOrdersFlow;
use App\WhatsApp\Flow\Definitions\ProfileFlow;
use App\WhatsApp\Flow\Definitions\RegistrationFlow;
use App\WhatsApp\Flow\Definitions\SettingsFlow;
use App\WhatsApp\Flow\Definitions\TrackOrderFlow;
use App\WhatsApp\Flow\Definitions\TransactionHistoryFlow;
use App\WhatsApp\Flow\Definitions\ViewTicketsFlow;
use App\WhatsApp\Flow\Definitions\WalletBalanceFlow;
use App\WhatsApp\Flow\FlowRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the WhatsApp assistant: a single shared FlowRegistry populated with all
 * available flows. Later waves append their flows to $flows.
 */
class WhatsAppServiceProvider extends ServiceProvider
{
    /** Flow classes registered with the engine, in menu order. */
    private const FLOWS = [
        WalletBalanceFlow::class,
        MyOrdersFlow::class,
        TrackOrderFlow::class,
        BrowseServicesFlow::class,
        TransactionHistoryFlow::class,
        // Wave 3 — auth:
        RegistrationFlow::class,
        LinkAccountFlow::class,
        ForgotPasswordFlow::class,
        // Wave 4 — money & support:
        CreateOrderFlow::class,
        DepositFundsFlow::class,
        CreateTicketFlow::class,
        ViewTicketsFlow::class,
        // Wave 5 — profile, settings, FAQ, AI:
        ProfileFlow::class,
        SettingsFlow::class,
        FaqFlow::class,
        AskAiFlow::class,
        // Growth:
        \App\WhatsApp\Flow\Definitions\ReferralFlow::class,
    ];

    public function register(): void
    {
        $this->app->singleton(FlowRegistry::class, function ($app) {
            $registry = new FlowRegistry;
            foreach (self::FLOWS as $flowClass) {
                $registry->register($app->make($flowClass));
            }

            return $registry;
        });
    }
}
