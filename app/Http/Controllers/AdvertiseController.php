<?php

namespace App\Http\Controllers;

use App\Models\AdvertBooking;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Web counterpart to the WhatsApp AdvertiseFlow: browse sponsored-advert
 * packages and pay for one from the wallet. Same shape — pick a package, see
 * the total, confirm — and the same money-safe charge (row-locked transaction,
 * debited only on explicit submit) and grandfather-price honoring for an
 * existing customer caught mid price-change. See AdvertBooking::priceFor().
 */
class AdvertiseController extends Controller
{
    public function index(): Response
    {
        $user = Auth::user();
        $since = $user->created_at;

        $packages = collect(AdvertBooking::packages())->map(function (array $pkg, string $key) use ($since) {
            $pkg['key'] = $key;
            $pkg['price'] = AdvertBooking::priceFor($key, $since);

            return $pkg;
        })->values();

        return Inertia::render('Advertise/Index', [
            'packages' => $packages,
            'recommended' => AdvertBooking::recommendedKey(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'package' => ['required', 'string'],
        ]);

        $pkg = AdvertBooking::package($data['package']);
        if (! $pkg) {
            return back()->withErrors(['package' => 'Please pick a valid package.']);
        }

        $user = Auth::user();
        $total = AdvertBooking::priceFor($data['package'], $user->created_at);
        $days = (int) ($pkg['days'] ?? 0);

        try {
            $booking = DB::transaction(function () use ($user, $data, $pkg, $days, $total): ?AdvertBooking {
                $locked = User::lockForUpdate()->find($user->id);
                if (! $locked || (float) $locked->balance < $total) {
                    return null;
                }

                $booking = AdvertBooking::create([
                    'user_id' => $locked->id,
                    'wa_phone' => WhatsAppAccount::where('user_id', $locked->id)->value('wa_phone'),
                    'package' => $data['package'],
                    'days' => $days,
                    'total' => $total,
                    'status' => 'pending_setup',
                ]);

                $charged = $locked->deductBalance(
                    $total,
                    null,
                    "Advert booking #{$booking->id} — {$pkg['label']}"
                );

                if (! $charged) {
                    throw new \RuntimeException('Advert charge failed inside transaction.');
                }

                return $booking;
            });
        } catch (\Throwable $e) {
            Log::error('Web advert booking failed', ['user_id' => $user->id, 'message' => $e->getMessage()]);

            return back()->withErrors(['package' => 'Something went wrong taking that payment — nothing was charged.']);
        }

        if (! $booking) {
            return back()->withErrors(['balance' => 'Insufficient balance for this package.']);
        }

        try {
            NotificationService::notifyAdmins(
                'admin_advert_booking',
                "New advert booking #{$booking->id}",
                "{$user->name} paid {$booking->total} for a {$booking->durationLabel()} advert"
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
            Log::warning('Advert booking admin notify failed', ['id' => $booking->id, 'message' => $e->getMessage()]);
        }

        return redirect()->route('advertise.index')
            ->with('success', "Booked! Your {$booking->packageLabel()} advert is paid for — our team will reach out to get your details.");
    }
}
