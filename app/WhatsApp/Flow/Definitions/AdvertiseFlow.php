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
 *   pick_package → ask_promoting → ask_link → ask_weeks → confirm → charge
 *
 * Priced flat per week (see config/adverts.php), paid from the wallet, and
 * fulfilled by a human — so it creates an AdvertBooking for the team rather
 * than dispatching to an upstream provider. Money-safe: the wallet is only
 * debited on an explicit confirmation, inside a transaction under a row lock.
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
        // Consume whatever the AI already gathered, then stop at the first gap.
        $package = mb_strtolower(trim((string) $ctx->pullPrefill('package')));
        if ($package !== '' && AdvertBooking::package($package)) {
            $ctx->set('ad_package', $package);
        }

        $promoting = trim((string) $ctx->pullPrefill('promoting'));
        if ($promoting !== '') {
            $ctx->set('ad_promoting', mb_substr($promoting, 0, 500));
        }

        $audience = trim((string) $ctx->pullPrefill('audience'));
        if ($audience !== '') {
            $ctx->set('ad_audience', mb_substr($audience, 0, 500));
        }

        $link = trim((string) $ctx->pullPrefill('link'));
        if ($link !== '') {
            $ctx->set('ad_link', mb_substr($link, 0, 500));
        }

        if (! $ctx->has('ad_package')) {
            return $this->packageMenu();
        }
        if (! $ctx->has('ad_promoting')) {
            return $this->askPromotingPrompt();
        }
        if (! $ctx->has('ad_audience')) {
            return $this->askAudiencePrompt();
        }
        if (! $ctx->has('ad_link')) {
            return $this->askLinkPrompt($ctx);
        }

        return $this->confirmPrompt($ctx);
    }

    public function handle(string $state, string $input, SessionContext $ctx): FlowResult
    {
        return match ($state) {
            'ask_promoting' => $this->askPromoting($input, $ctx),
            'ask_audience' => $this->askAudience($input, $ctx),
            'ask_link' => $this->askLink($input, $ctx),
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
            "📣 *Sponsored adverts*\n\nWe run the campaign for you on Facebook & Instagram to put you in front of new customers.\n\nPick a package — from a quick 1-day test to a full month:",
            'pick_package'
        )->withList('Choose package', [['title' => 'Packages', 'rows' => $rows]], 'Advertise', 'Flat price — no hidden extras');
    }

    private function pickPackage(string $input, SessionContext $ctx): FlowResult
    {
        $keys = $this->keys();
        $idx = (int) preg_replace('/\D+/', '', $input) - 1;
        $key = $keys[$idx] ?? null;

        // Also accept the package said by name ("standard", "the 30 one").
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

        return $this->askPromotingPrompt();
    }

    private function askPromotingPrompt(): FlowResult
    {
        return FlowResult::step(
            "Great choice! 🎯 What are we advertising?\n\nTell me in a line — e.g. *\"my salon in Chitungwiza\"*, *\"a phone shop\"*, *\"an event on Saturday\"*.",
            'ask_promoting'
        );
    }

    private function askPromoting(string $input, SessionContext $ctx): FlowResult
    {
        $text = trim($input);
        if (mb_strlen($text) < 3) {
            return FlowResult::retry('Tell me briefly what you\'re advertising (a shop, a product, an event…), or type *cancel*.', 'ask_promoting');
        }

        $ctx->set('ad_promoting', mb_substr($text, 0, 500));

        return $this->askAudiencePrompt();
    }

    private function askAudiencePrompt(): FlowResult
    {
        return FlowResult::step(
            "🎯 Who should we put this in front of?\n\nTell me the *areas* (e.g. *Ruwa, Zimre Park, Damafalls*) and, if it helps, the kind of people — e.g. *\"parents of young kids\"*, *\"anyone in Harare\"*.",
            'ask_audience'
        );
    }

    private function askAudience(string $input, SessionContext $ctx): FlowResult
    {
        $text = trim($input);
        if (mb_strlen($text) < 2) {
            return FlowResult::retry('Just tell me the areas or people to target (e.g. *Ruwa, Eastview* or *parents in Harare*), or type *cancel*.', 'ask_audience');
        }

        $ctx->set('ad_audience', mb_substr($text, 0, 500));

        return $this->askLinkPrompt($ctx);
    }

    private function askLinkPrompt(SessionContext $ctx): FlowResult
    {
        $pkg = AdvertBooking::package((string) $ctx->get('ad_package')) ?? [];

        $body = ! empty($pkg['includes_video'])
            // Video is made for them — we just need where to send the traffic.
            ? "👍 Send your Facebook/Instagram *page link* so we point the advert there — we'll create the video for you. 🎬\n\nDon't have it handy? Reply *skip* and our team will sort it with you."
            : "👍 Now send the *link* to the post or video you want boosted.\n\nNo link yet? Reply *skip* and our team will build the advert with you.";

        return FlowResult::step($body, 'ask_link')->withButtons([['id' => 'fs:skip', 'title' => 'Skip for now']]);
    }

    private function askLink(string $input, SessionContext $ctx): FlowResult
    {
        $t = mb_strtolower(trim($input));
        $ctx->set('ad_link', in_array($t, ['skip', 'none', 'no', 'later'], true) ? '' : mb_substr(trim($input), 0, 500));

        return $this->confirmPrompt($ctx);
    }

    private function confirmPrompt(SessionContext $ctx): FlowResult
    {
        $user = $this->user($ctx);
        $cur = $this->currency($ctx);
        $pkg = AdvertBooking::package((string) $ctx->get('ad_package')) ?? [];
        $total = round((float) ($pkg['price'] ?? 0), 2);
        $balance = (float) ($user?->balance ?? 0);

        $link = (string) $ctx->get('ad_link', '');
        $audience = (string) $ctx->get('ad_audience', '');
        $video = ! empty($pkg['includes_video']);
        $summary = "🧾 *Confirm your advert*\n\n"
            ."Package: *{$pkg['label']}*".($video ? ' 🎬 _(includes a custom video advert)_' : '')."\n"
            .'Promoting: '.$ctx->get('ad_promoting')."\n"
            .($audience !== '' ? "Target: {$audience}\n" : '')
            .($link !== '' ? "Link: {$link}\n" : "Link: _to be arranged with our team_\n")
            .'Total: *'.$this->money($total, $cur)."*\n"
            .'Balance: '.$this->money($balance, $cur)."\n\n";

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

        return FlowResult::step($summary.'Start this campaign?', 'confirm')->withButtons([
            ['id' => 'fs:yes', 'title' => '✅ Start advert'],
            ['id' => 'fs:cancel', 'title' => '✖ Cancel'],
        ]);
    }

    private function confirm(string $input, SessionContext $ctx): FlowResult
    {
        $t = mb_strtolower(trim($input));

        if (! in_array($t, ['yes', 'y', 'confirm', 'ok', 'start', 'yebo', 'ehe'], true)) {
            if (in_array($t, ['no', 'n', 'cancel', 'stop', 'kwete', 'hatshi'], true)) {
                return FlowResult::fail('No problem — advert cancelled. Type *advertise* whenever you\'re ready.');
            }

            // Anything else (e.g. "make it 3 weeks") goes to the AI to adjust.
            return FlowResult::retry('Tap *✅ Start advert* (or reply *YES*) to confirm — or *✖ Cancel* to stop.', 'confirm')
                ->withButtons([
                    ['id' => 'fs:yes', 'title' => '✅ Start advert'],
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
                    'promoting' => (string) $ctx->get('ad_promoting'),
                    'target_link' => (string) $ctx->get('ad_link', '') ?: null,
                    'target_audience' => (string) $ctx->get('ad_audience', '') ?: null,
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
            "🎉 *Your advert is booked!*\n\n"
            ."📣 *{$pkg['label']}* advert".($video ? ' 🎬 with a custom video' : '')."\n"
            .'Total paid: *'.$this->money($total, $cur)."*\n"
            .'Reference: *#'.$booking->id."*\n\n"
            .($video
                ? "Our team will *create your video advert* and get it live shortly — we'll message you right here when it's running, and ask if we need a photo, logo or details. 🚀"
                : "Our team will set up your campaign and get it live shortly — we'll message you right here when it's running. If we need anything else, we'll ask. 🚀")
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
                .($booking->includesVideo() ? ' 🎬 (MAKE A VIDEO for this one). ' : ' (boost-only). ')
                ."Promoting: {$booking->promoting}. "
                .($booking->target_audience ? "Target: {$booking->target_audience}. " : 'No target specified. ')
                .($booking->target_link ? "Link: {$booking->target_link}. " : 'No link supplied yet. ')
                .'Set the campaign up and reply to them on WhatsApp.',
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
