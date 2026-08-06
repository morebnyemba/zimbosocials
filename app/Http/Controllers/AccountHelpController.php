<?php

namespace App\Http\Controllers;

use App\Models\ExtraServiceBooking;
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
 * Web counterpart to the WhatsApp AccountHelpFlow: browse one-off account /
 * advertising support services and pay for one from the wallet. Same shape
 * and same money-safe charge (row-locked transaction, debited only on
 * explicit submit) as AdvertiseController.
 */
class AccountHelpController extends Controller
{
    public function index(): Response
    {
        $items = collect(ExtraServiceBooking::items())->map(function (array $item, string $key) {
            $item['key'] = $key;

            return $item;
        })->values();

        return Inertia::render('AccountHelp/Index', [
            'items' => $items,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'item' => ['required', 'string'],
        ]);

        $item = ExtraServiceBooking::item($data['item']);
        if (! $item) {
            return back()->withErrors(['item' => 'Please pick a valid service.']);
        }

        $user = Auth::user();
        $total = round((float) $item['price'], 2);

        try {
            $booking = DB::transaction(function () use ($user, $data, $item, $total): ?ExtraServiceBooking {
                $locked = User::lockForUpdate()->find($user->id);
                if (! $locked || (float) $locked->balance < $total) {
                    return null;
                }

                $booking = ExtraServiceBooking::create([
                    'user_id' => $locked->id,
                    'wa_phone' => WhatsAppAccount::where('user_id', $locked->id)->value('wa_phone'),
                    'item' => $data['item'],
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
            Log::error('Web account-help booking failed', ['user_id' => $user->id, 'message' => $e->getMessage()]);

            return back()->withErrors(['item' => 'Something went wrong taking that payment — nothing was charged.']);
        }

        if (! $booking) {
            return back()->withErrors(['balance' => 'Insufficient balance for this service.']);
        }

        try {
            NotificationService::notifyAdmins(
                'admin_account_help_booking',
                "New account-help booking #{$booking->id}",
                "{$user->name} paid {$booking->total} for: {$booking->label()}. MESSAGE THEM to get started.",
                [
                    'extra_service_booking_id' => $booking->id,
                    'wa_phone' => $booking->wa_phone,
                    'amount' => (string) $booking->total,
                ],
            );
        } catch (\Throwable $e) {
            Log::warning('Account-help booking admin notify failed', ['id' => $booking->id, 'message' => $e->getMessage()]);
        }

        return redirect()->route('account-help.index')
            ->with('success', "Paid! You're booked in for {$booking->label()} — our team will reach out to get started.");
    }
}
