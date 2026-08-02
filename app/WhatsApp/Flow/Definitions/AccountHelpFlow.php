<?php

namespace App\WhatsApp\Flow\Definitions;

use App\Models\ExtraServiceBooking;
use App\Services\NotificationService;
use App\WhatsApp\Flow\AbstractFlow;
use App\WhatsApp\Flow\FlowResult;
use App\WhatsApp\Session\SessionContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Request and PAY for a one-off account/advertising support service —
 * advertising setup & training, an ads account fix, or account recovery &
 * unbanning (config/extra_services.php). Flow id: 'account_help'.
 *   pick_item → confirm → charge
 *
 * Same shape as AdvertiseFlow: the customer only picks and pays, the team
 * follows up afterwards to actually do the work. Money-safe: the wallet is
 * only debited on an explicit confirmation, inside a transaction under a
 * row lock.
 */
class AccountHelpFlow extends AbstractFlow
{
    public function id(): string
    {
        return 'account_help';
    }

    public function entryState(): string
    {
        return 'pick_item';
    }

    public function authRequired(): bool
    {
        return true;
    }

    /** Ordered item keys, so a tapped/typed number maps to an item. */
    private function keys(): array
    {
        return array_keys(ExtraServiceBooking::items());
    }

    public function prompt(string $state, SessionContext $ctx): FlowResult
    {
        // The AI may already have resolved an item — consume it and jump
        // straight to payment.
        $item = mb_strtolower(trim((string) $ctx->pullPrefill('item')));
        if ($item !== '' && ExtraServiceBooking::item($item)) {
            $ctx->set('help_item', $item);
        }

        if (! $ctx->has('help_item')) {
            return $this->itemMenu();
        }

        return $this->confirmPrompt($ctx);
    }

    public function handle(string $state, string $input, SessionContext $ctx): FlowResult
    {
        return match ($state) {
            'confirm' => $this->confirm($input, $ctx),
            default => $this->pickItem($input, $ctx),
        };
    }

    // ── Steps ────────────────────────────────────────────────────────────────

    private function itemMenu(): FlowResult
    {
        $rows = [];
        $i = 1;
        foreach (ExtraServiceBooking::items() as $item) {
            $price = '$'.number_format((float) $item['price'], 2);
            $rows[] = [
                'id' => 'fs:'.$i,
                'title' => $item['label'].' — '.$price,
                'description' => $item['blurb'] ?? '',
            ];
            $i++;
        }

        return FlowResult::step(
            "🛠️ *Account & advertising support*\n\nOne-off help outside our usual growth packages — pick what you need:",
            'pick_item'
        )->withList('Choose one', [['title' => 'Services', 'rows' => $rows]], 'Get help', 'Flat price — no hidden extras');
    }

    private function pickItem(string $input, SessionContext $ctx): FlowResult
    {
        $keys = $this->keys();
        $idx = (int) preg_replace('/\D+/', '', $input) - 1;
        $key = $keys[$idx] ?? null;

        // Also accept the item said by name.
        if ($key === null) {
            $t = mb_strtolower($input);
            foreach (ExtraServiceBooking::items() as $k => $item) {
                if (str_contains($t, mb_strtolower((string) $item['label'])) || str_contains($t, $k)) {
                    $key = $k;
                    break;
                }
            }
        }

        if ($key === null) {
            return FlowResult::retry('Please pick one of the options by number, or type *cancel*.', 'pick_item');
        }

        $ctx->set('help_item', $key);

        return $this->confirmPrompt($ctx);
    }

    private function confirmPrompt(SessionContext $ctx): FlowResult
    {
        $user = $this->user($ctx);
        $cur = $this->currency($ctx);
        $key = (string) $ctx->get('help_item');
        $item = ExtraServiceBooking::item($key) ?? [];
        $total = round((float) ($item['price'] ?? 0), 2);
        $balance = (float) ($user?->balance ?? 0);

        $summary = "🧾 *Confirm*\n\n"
            ."Service: *{$item['label']}*\n"
            .'Total: *'.$this->money($total, $cur)."*\n"
            .'Balance: '.$this->money($balance, $cur)."\n\n"
            .'Once you pay, our team messages you here to get started. 🚀'."\n\n";

        if ($balance < $total) {
            $short = round($total - $balance, 2);
            $ctx->set('_prefill_amount', $short);

            return FlowResult::step(
                $summary."⚠️ You're a bit short — you need *".$this->money($short, $cur)."* more.\n\n"
                .'Top up first (I\'ve got the amount ready 👍), then confirm again.',
                'confirm'
            )->withButtons([
                ['id' => 'fl_deposit', 'title' => '💰 Deposit'],
                ['id' => 'fs:cancel', 'title' => '✖ Cancel'],
            ]);
        }

        return FlowResult::step($summary.'Pay now?', 'confirm')->withButtons([
            ['id' => 'fs:yes', 'title' => '✅ Pay'],
            ['id' => 'fs:cancel', 'title' => '✖ Cancel'],
        ]);
    }

    private function confirm(string $input, SessionContext $ctx): FlowResult
    {
        $t = mb_strtolower(trim($input));

        if (! in_array($t, ['yes', 'y', 'confirm', 'ok', 'pay', 'start', 'yebo', 'ehe'], true)) {
            if (in_array($t, ['no', 'n', 'cancel', 'stop', 'kwete', 'hatshi'], true)) {
                return FlowResult::fail('No problem — nothing charged. Type *support* whenever you\'re ready.');
            }

            return FlowResult::retry('Tap *✅ Pay* (or reply *YES*) to confirm — or *✖ Cancel* to stop.', 'confirm')
                ->withButtons([
                    ['id' => 'fs:yes', 'title' => '✅ Pay'],
                    ['id' => 'fs:cancel', 'title' => '✖ Cancel'],
                ]);
        }

        $user = $this->user($ctx);
        if (! $user) {
            return FlowResult::fail('Please try again from the *menu*.');
        }

        $key = (string) $ctx->get('help_item');
        $item = ExtraServiceBooking::item($key);
        if (! $item) {
            return FlowResult::fail('Something went wrong setting that up. Type *support* to start again.');
        }

        $total = round((float) $item['price'], 2);
        $cur = $this->currency($ctx);

        try {
            $booking = DB::transaction(function () use ($user, $ctx, $key, $item, $total): ?ExtraServiceBooking {
                $locked = \App\Models\User::lockForUpdate()->find($user->id);
                if (! $locked || (float) $locked->balance < $total) {
                    return null; // funds moved between confirm and tap
                }

                $booking = ExtraServiceBooking::create([
                    'user_id' => $locked->id,
                    'wa_phone' => $ctx->phone,
                    'item' => $key,
                    'total' => $total,
                    'status' => 'pending_setup',
                ]);

                $charged = $locked->deductBalance(
                    $total,
                    null,
                    "Account help #{$booking->id} — {$item['label']}"
                );

                if (! $charged) {
                    throw new \RuntimeException('Account-help charge failed inside transaction.');
                }

                return $booking;
            });
        } catch (\Throwable $e) {
            Log::error('Account-help booking failed', ['phone' => $ctx->phone, 'message' => $e->getMessage()]);

            return FlowResult::fail('⚠️ Something went wrong taking that payment — nothing was charged. Type *support* to try again.');
        }

        if (! $booking) {
            return FlowResult::fail('Your balance changed — top up and type *support* to finish. No money was taken.');
        }

        $this->alertTeam($booking, $user->name ?? 'Customer');

        return FlowResult::complete(
            "🎉 *Paid — you're booked in!*\n\n"
            ."🛠️ *{$item['label']}*\n"
            .'Total paid: *'.$this->money($total, $cur)."*\n"
            .'Reference: *#'.$booking->id."*\n\n"
            .'Our team will message you *right here* shortly to get started. 🚀'
        );
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function alertTeam(ExtraServiceBooking $booking, string $customer): void
    {
        try {
            NotificationService::notifyAdmins(
                'admin_account_help_booking',
                "New account-help booking #{$booking->id}",
                "{$customer} paid {$booking->total} for: {$booking->label()}. MESSAGE THEM to get started.",
                [
                    'extra_service_booking_id' => $booking->id,
                    'wa_phone' => $booking->wa_phone,
                    'amount' => (string) $booking->total,
                ],
            );
        } catch (\Throwable $e) {
            // The booking is paid for — never fail it on a notification hiccup.
            Log::warning('Account-help booking admin notify failed', ['id' => $booking->id, 'message' => $e->getMessage()]);
        }
    }

    private function currency(SessionContext $ctx): string
    {
        return $this->user($ctx)?->currency ?? 'USD';
    }
}
