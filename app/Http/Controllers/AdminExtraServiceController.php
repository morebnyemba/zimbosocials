<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ExtraServiceBooking;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Services\NotificationService;
use App\WhatsApp\Messaging\WhatsAppGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin console for paid one-off account/advertising support bookings
 * (config/extra_services.php). These are fulfilled by a human, same pattern
 * as AdminAdvertController — a booking moves from 'pending_setup' to
 * 'in_progress' and 'completed', or gets refunded if we can't do the work.
 */
class AdminExtraServiceController extends Controller
{
    public const STATUSES = ['pending_setup', 'in_progress', 'completed', 'cancelled'];

    public function index(Request $request): Response
    {
        $query = ExtraServiceBooking::query()->with('user:id,name,email');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('wa_phone', 'like', "%{$search}%")
                    ->orWhere('id', $search)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            });
        }

        $counts = ExtraServiceBooking::selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');

        return Inertia::render('Admin/ExtraServices/Index', [
            'bookings' => $query->latest()->paginate(20)->withQueryString(),
            'filters' => $request->only(['status', 'search']),
            'statuses' => self::STATUSES,
            'stats' => [
                'pending_setup' => (int) ($counts['pending_setup'] ?? 0),
                'in_progress' => (int) ($counts['in_progress'] ?? 0),
                'completed' => (int) ($counts['completed'] ?? 0),
                'cancelled' => (int) ($counts['cancelled'] ?? 0),
                'revenue' => (float) ExtraServiceBooking::whereIn('status', ['pending_setup', 'in_progress', 'completed'])->sum('total'),
            ],
        ]);
    }

    public function updateStatus(Request $request, ExtraServiceBooking $extraService): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $old = $extraService->status;
        $extraService->update(['status' => $data['status'], 'notes' => $data['notes'] ?? $extraService->notes]);

        AuditLog::log('extra_service.status_changed', Auth::id(), ExtraServiceBooking::class, $extraService->id,
            ['status' => $old], ['status' => $extraService->status]);

        $message = match ($data['status']) {
            'in_progress' => "🛠️ We've started work on your *{$extraService->label()}* — we'll update you here as it progresses.",
            'completed' => "✅ Your *{$extraService->label()}* is done! Thanks for booking with us. "
                .'Reply *accounthelp* if you need anything else.',
            default => null,
        };

        if ($message !== null) {
            $this->messageCustomer($extraService, $message);
        }

        return back()->with('success', "Booking #{$extraService->id} marked {$data['status']}.");
    }

    /** Cancel a booking we can't do and put the money back in their wallet. */
    public function refund(ExtraServiceBooking $extraService): RedirectResponse
    {
        if ($extraService->status === 'cancelled') {
            return back()->with('error', 'That booking is already cancelled.');
        }

        $amount = (float) $extraService->total;

        DB::transaction(function () use ($extraService, $amount): void {
            $locked = ExtraServiceBooking::lockForUpdate()->find($extraService->id);
            if (! $locked || $locked->status === 'cancelled') {
                return;
            }

            $user = User::lockForUpdate()->find($locked->user_id);
            if ($user) {
                $user->creditBalance($amount, 'refund', "Refund: account-help booking #{$locked->id}", 'refund');
            }

            $locked->update(['status' => 'cancelled']);
        });

        AuditLog::log('extra_service.refunded', Auth::id(), ExtraServiceBooking::class, $extraService->id, null, ['amount' => $amount]);

        NotificationService::notify(
            (int) $extraService->user_id,
            'order_refunded',
            'Account-help booking refunded',
            "Your booking #{$extraService->id} was cancelled and {$amount} was returned to your wallet.",
            ['extra_service_booking_id' => $extraService->id, 'refund_amount' => $amount],
        );

        $this->messageCustomer($extraService, "We've cancelled your *{$extraService->label()}* booking and returned "
            .'*'.number_format($amount, 2).'* to your wallet. Sorry about that — reply here if you\'d like to try something else. 🙏');

        return back()->with('success', "Booking #{$extraService->id} cancelled and refunded.");
    }

    /** Send the customer a WhatsApp note about their booking. */
    public function message(Request $request, ExtraServiceBooking $extraService): RedirectResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:2000']]);

        return $this->messageCustomer($extraService, $data['message'])
            ? back()->with('success', 'Message sent.')
            : back()->with('error', 'Could not send the message — check the WhatsApp connection (or this booking has no WhatsApp number).');
    }

    private function messageCustomer(ExtraServiceBooking $extraService, string $body): bool
    {
        $phone = (string) $extraService->wa_phone;
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
            'intent' => 'account_help_update',
        ]);

        return true;
    }
}
