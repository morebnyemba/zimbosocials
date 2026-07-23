<?php

namespace App\WhatsApp\Flow\Definitions;

use App\Models\AdvertBooking;
use App\Services\NotificationService;
use App\WhatsApp\Flow\AbstractFlow;
use App\WhatsApp\Flow\FlowResult;
use App\WhatsApp\Session\SessionContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Book and PAY for a sponsored advert campaign. Flow id: 'advertise'.
 *   pick_package → confirm → charge
 *
 * Deliberately minimal: the customer only picks a package and pays. Everything
 * else — what they're promoting, their page, the areas to target — is gathered
 * by the team afterwards (and the video, on the tiers that include one, is
 * AI-generated). Flat-priced (config/adverts.php), paid from the wallet, and
 * fulfilled by a human, so it creates an AdvertBooking for the team rather than
 * dispatching upstream. Money-safe: the wallet is only debited on an explicit
 * confirmation, inside a transaction under a row lock.
 */
class AdvertiseFlow extends AbstractFlow
{
    public function id(): string
    {
        return 'advertise';
    }

    public function entryState(): string
    {
        return 'pick_package';
    }

    public function authRequired(): bool
    {
        return true;
    }

    /** Ordered package keys, so a tapped/typed number maps to a package. */
    private function keys(): array
    {
        return array_keys(AdvertBooking::packages());
    }

    public function prompt(string $state, SessionContext $ctx): FlowResult
    {
        // The AI may already have recommended a package — consume it and jump
        // straight to payment. Nothing else is collected here.
        $package = mb_strtolower(trim((string) $ctx->pullPrefill('package')));
        if ($package !== '' && AdvertBooking::package($package)) {
            $ctx->set('ad_package', $package);
        }

        if (! $ctx->has('ad_package')) {
            return $this->packageMenu();
        }

        return $this->confirmPrompt($ctx);
    }

    public function handle(string $state, string $input, SessionContext $ctx): FlowResult
    {
        return match ($state) {
            'confirm' => $this->confirm($input, $ctx),
            default => $this->pickPackage($input, $ctx),
        };
    }

    // ── Steps ────────────────────────────────────────────────────────────────

    private function packageMenu(): FlowResult
    {
        $rows = [];
        $i = 1;
        foreach (AdvertBooking::packages() as $pkg) {
            $price = '$'.number_format((float) $pkg['price'], 2);
            $tag = ! empty($pkg['includes_video']) ? '🎬 ' : '';
            $rows[] = [
                'id' => 'fs:'.$i,
                'title' => $pkg['label'].' — '.$price,
                'description' => $tag.(! empty($pkg['recommended']) ? '⭐ ' : '').($pkg['blurb'] ?? ''),
            ];
            $i++;
        }

        return FlowResult::step(
            "📣 *Sponsored adverts*\n\nWe run the campaign for you on Facebook & Instagram to put you in front of new customers.\n\nPick a package — from a quick 1-day test to a full month. 🎬 = we make you an AI video advert:",
            'pick_package'
        )->withList('Choose package', [['title' => 'Packages', 'rows' => $rows]], 'Advertise', 'Flat price — no hidden extras');
    }

    private function pickPackage(string $input, SessionContext $ctx): FlowResult
    {
        $keys = $this->keys();
        $idx = (int) preg_replace('/\D+/', '', $input) - 1;
        $key = $keys[$idx] ?? null;

        // Also accept the package said by name ("1 week", "the 20 one").
        if ($key === null) {
            $t = mb_strtolower($input);
            foreach (AdvertBooking::packages() as $k => $pkg) {
                if (str_contains($t, mb_strtolower((string) $pkg['label'])) || str_contains($t, $k)) {
                    $key = $k;
                    break;
                }
            }
        }

        if ($key === null) {
            return FlowResult::retry('Please pick one of the packages by number, or type *cancel*.', 'pick_package');
        }

        $ctx->set('ad_package', $key);

        return $this->confirmPrompt($ctx);
    }

    private function confirmPrompt(SessionContext $ctx): FlowResult
    {
        $user = $this->user($ctx);
        $cur = $this->currency($ctx);
        $pkg = AdvertBooking::package((string) $ctx->get('ad_package')) ?? [];
        $total = round((float) ($pkg['price'] ?? 0), 2);
        $balance = (float) ($user?->balance ?? 0);
        $video = ! empty($pkg['includes_video']);

        $summary = "🧾 *Confirm your advert*\n\n"
            ."Package: *{$pkg['label']}*".($video ? ' 🎬 _(includes an AI video advert)_' : '')."\n"
            .'Total: *'.$this->money($total, $cur)."*\n"
            .'Balance: '.$this->money($balance, $cur)."\n\n"
            ."Once you pay, our team messages you here to get your advert details (what you're promoting, your page, the areas to target)"
            .($video ? ' and create your AI video' : '').". 🚀\n\n";

        if ($balance < $total) {
            $short = round($total - $balance, 2);
            // Hand the exact shortfall to the deposit flow, same as an order.
            $ctx->set('_prefill_amount', $short);

            return FlowResult::step(
                $summary."⚠️ You're a bit short — you need *".$this->money($short, $cur)."* more.\n\n"
                .'Top up first (I\'ve got the amount ready 👍), then confirm your advert.',
                'confirm'
            )->withButtons([
                ['id' => 'fl_deposit', 'title' => '💰 Deposit'],
                ['id' => 'fs:cancel', 'title' => '✖ Cancel'],
            ]);
        }

        return FlowResult::step($summary.'Book & pay now?', 'confirm')->withButtons([
            ['id' => 'fs:yes', 'title' => '✅ Pay & book'],
            ['id' => 'fs:cancel', 'title' => '✖ Cancel'],
        ]);
    }

    private function confirm(string $input, SessionContext $ctx): FlowResult
    {
        $t = mb_strtolower(trim($input));

        if (! in_array($t, ['yes', 'y', 'confirm', 'ok', 'pay', 'book', 'start', 'yebo', 'ehe'], true)) {
            if (in_array($t, ['no', 'n', 'cancel', 'stop', 'kwete', 'hatshi'], true)) {
                return FlowResult::fail('No problem — advert cancelled. Type *advertise* whenever you\'re ready.');
            }

            return FlowResult::retry('Tap *✅ Pay & book* (or reply *YES*) to confirm — or *✖ Cancel* to stop.', 'confirm')
                ->withButtons([
                    ['id' => 'fs:yes', 'title' => '✅ Pay & book'],
                    ['id' => 'fs:cancel', 'title' => '✖ Cancel'],
                ]);
        }

        $user = $this->user($ctx);
        if (! $user) {
            return FlowResult::fail('Please try again from the *menu*.');
        }

        $key = (string) $ctx->get('ad_package');
        $pkg = AdvertBooking::package($key);
        if (! $pkg) {
            return FlowResult::fail('Something went wrong setting that up. Type *advertise* to start again.');
        }

        $days = (int) ($pkg['days'] ?? 0);
        $total = round((float) $pkg['price'], 2);
        $cur = $this->currency($ctx);

        try {
            $booking = DB::transaction(function () use ($user, $ctx, $key, $pkg, $days, $total): ?AdvertBooking {
                $locked = \App\Models\User::lockForUpdate()->find($user->id);
                if (! $locked || (float) $locked->balance < $total) {
                    return null; // funds moved between confirm and tap
                }

                $booking = AdvertBooking::create([
                    'user_id' => $locked->id,
                    'wa_phone' => $ctx->phone,
                    'package' => $key,
                    'days' => $days,
                    'total' => $total,
                    'status' => 'pending_setup',
                ]);

                $charged = $locked->deductBalance(
                    $total,
                    null, // not a catalogue order — referenced in the note instead
                    "Advert booking #{$booking->id} — {$pkg['label']}"
                );

                if (! $charged) {
                    throw new \RuntimeException('Advert charge failed inside transaction.');
                }

                return $booking;
            });
        } catch (\Throwable $e) {
            Log::error('Advert booking failed', ['phone' => $ctx->phone, 'message' => $e->getMessage()]);

            return FlowResult::fail('⚠️ Something went wrong taking that payment — nothing was charged. Type *advertise* to try again.');
        }

        if (! $booking) {
            return FlowResult::fail('Your balance changed — top up and type *advertise* to finish. No money was taken.');
        }

        $this->alertTeam($booking, $user->name ?? 'Customer');

        $video = ! empty($pkg['includes_video']);

        return FlowResult::complete(
            "🎉 *Your advert is booked & paid!*\n\n"
            ."📣 *{$pkg['label']}* advert".($video ? ' 🎬 with an AI video' : '')."\n"
            .'Total paid: *'.$this->money($total, $cur)."*\n"
            .'Reference: *#'.$booking->id."*\n\n"
            ."Our team will message you *right here* shortly to get your details — what you're promoting, your Facebook/Instagram page, and the areas to target"
            .($video ? " — then create your AI video advert and get it live." : " — then get it live.")
            .' 🚀'
        );
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function alertTeam(AdvertBooking $booking, string $customer): void
    {
        try {
            NotificationService::notifyAdmins(
                'admin_advert_booking',
                "New advert booking #{$booking->id}",
                "{$customer} paid {$booking->total} for a {$booking->durationLabel()} advert"
                .($booking->includesVideo() ? ' 🎬 (GENERATE AI VIDEO). ' : ' (boost-only). ')
                .'MESSAGE THEM to collect: what they\'re promoting, their page link, and the target areas. '
                .'Then set the campaign up and reply on WhatsApp.',
                [
                    'advert_booking_id' => $booking->id,
                    'wa_phone' => $booking->wa_phone,
                    'amount' => (string) $booking->total,
                ],
            );
        } catch (\Throwable $e) {
            // The booking is paid for — never fail it on a notification hiccup.
            Log::warning('Advert booking admin notify failed', ['id' => $booking->id, 'message' => $e->getMessage()]);
        }
    }

    private function currency(SessionContext $ctx): string
    {
        return $this->user($ctx)?->currency ?? 'USD';
    }
}
