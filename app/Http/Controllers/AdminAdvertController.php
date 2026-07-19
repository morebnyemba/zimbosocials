<?php

namespace App\Http\Controllers;

use App\Models\AdvertBooking;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\NotificationService;
use App\WhatsApp\Messaging\WhatsAppGateway;
use App\Models\WhatsAppMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin console for paid sponsored-advert bookings. These are fulfilled by a
 * human (the team builds the campaign), so this is where a booking moves from
 * 'pending_setup' to live and finished — and where it gets refunded if we
 * can't run it after all.
 */
class AdminAdvertController extends Controller
{
    public const STATUSES = ['pending_setup', 'active', 'completed', 'cancelled'];

    public function index(Request $request): Response
    {
        $query = AdvertBooking::query()->with('user:id,name,email');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('promoting', 'like', "%{$search}%")
                    ->orWhere('wa_phone', 'like', "%{$search}%")
                    ->orWhere('id', $search)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            });
        }

        $counts = AdvertBooking::selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');

        return Inertia::render('Admin/Adverts/Index', [
            'bookings' => $query->latest()->paginate(20)->withQueryString(),
            'filters' => $request->only(['status', 'search']),
            'statuses' => self::STATUSES,
            'stats' => [
                'pending_setup' => (int) ($counts['pending_setup'] ?? 0),
                'active' => (int) ($counts['active'] ?? 0),
                'completed' => (int) ($counts['completed'] ?? 0),
                'cancelled' => (int) ($counts['cancelled'] ?? 0),
                'revenue' => (float) AdvertBooking::whereIn('status', ['pending_setup', 'active', 'completed'])->sum('total'),
            ],
        ]);
    }

    public function updateStatus(Request $request, AdvertBooking $advert): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['pending_setup', 'active', 'completed'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $old = $advert->status;
        $advert->update(['status' => $data['status'], 'notes' => $data['notes'] ?? $advert->notes]);

        AuditLog::log('advert.status_changed', Auth::id(), AdvertBooking::class, $advert->id,
            ['status' => $old], ['status' => $advert->status]);

        // Tell the customer when their campaign actually goes live / wraps up.
        $message = match ($data['status']) {
            'active' => "🚀 Your *{$advert->packageLabel()}* advert is now LIVE! It runs for {$advert->weeks} week"
                .($advert->weeks > 1 ? 's' : '').". We'll keep an eye on it — reply here any time with questions.",
            'completed' => "✅ Your *{$advert->packageLabel()}* advert campaign has finished. Thanks for advertising with us! "
                .'Reply *advertise* to run another one.',
            default => null,
        };

        if ($message !== null) {
            $this->messageCustomer($advert, $message);
        }

        return back()->with('success', "Advert #{$advert->id} marked {$data['status']}.");
    }

    /** Cancel a booking we can't run and put the money back in their wallet. */
    public function refund(AdvertBooking $advert): RedirectResponse
    {
        if ($advert->status === 'cancelled') {
            return back()->with('error', 'That booking is already cancelled.');
        }

        $amount = (float) $advert->total;

        DB::transaction(function () use ($advert, $amount): void {
            $locked = AdvertBooking::lockForUpdate()->find($advert->id);
            if (! $locked || $locked->status === 'cancelled') {
                return;
            }

            $user = User::lockForUpdate()->find($locked->user_id);
            if ($user) {
                $user->creditBalance($amount, 'refund', "Refund: advert booking #{$locked->id}", 'refund');
            }

            $locked->update(['status' => 'cancelled']);
        });

        AuditLog::log('advert.refunded', Auth::id(), AdvertBooking::class, $advert->id, null, ['amount' => $amount]);

        NotificationService::notify(
            (int) $advert->user_id,
            'order_refunded',
            'Advert booking refunded',
            "Your advert booking #{$advert->id} was cancelled and {$amount} was returned to your wallet.",
            ['advert_booking_id' => $advert->id, 'refund_amount' => $amount],
        );

        $this->messageCustomer($advert, "We've cancelled your *{$advert->packageLabel()}* advert booking and returned "
            .'*'.number_format($amount, 2).'* to your wallet. Sorry about that — reply here if you\'d like to try something else. 🙏');

        return back()->with('success', "Advert #{$advert->id} cancelled and refunded.");
    }

    /** Send the customer a WhatsApp note about their booking. */
    public function message(Request $request, AdvertBooking $advert): RedirectResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:2000']]);

        return $this->messageCustomer($advert, $data['message'])
            ? back()->with('success', 'Message sent.')
            : back()->with('error', 'Could not send the message — check the WhatsApp connection.');
    }

    private function messageCustomer(AdvertBooking $advert, string $body): bool
    {
        $phone = (string) $advert->wa_phone;
        if ($phone === '') {
            return false;
        }

        $res = app(WhatsAppGateway::class)->sendText($phone, $body);
        if (empty($res['ok'])) {
            return false;
        }

        WhatsAppMessage::create([
            'wa_phone' => $phone,
            'direction' => 'out',
            'wa_message_id' => $res['message_id'] ?? null,
            'msg_type' => 'text',
            'body' => $body,
            'handled_by' => 'agent',
            'intent' => 'advert_update',
        ]);

        return true;
    }
}
