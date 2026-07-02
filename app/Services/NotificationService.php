<?php

namespace App\Services;

use App\Jobs\SendEmailNotification;
use App\Jobs\SendWhatsAppNotification;
use App\Models\Notification;
use App\Models\User;

/**
 * Central notification orchestrator — dispatches to in-app + email + WhatsApp.
 *
 * Usage:
 *   NotificationService::notify($userId, 'deposit_confirmed', 'Deposit Confirmed',
 *       'Your deposit of $10.00 has been credited.', ['amount' => '$10.00']);
 */
class NotificationService
{
    /**
     * Types that should also trigger WhatsApp (critical / money-related).
     */
    private const WHATSAPP_TYPES = [
        'deposit_confirmed',
        'deposit_rejected',
        'withdrawal_processed',
        'withdrawal_rejected',
        'order_refunded',
        'balance_adjusted',
        'role_changed',
        'order_status_changed',
        'contract_application',
        'admin_new_registration',
        'admin_new_order',
    ];

    /**
     * Types that should also trigger email.
     */
    private const EMAIL_TYPES = [
        'deposit_confirmed',
        'deposit_rejected',
        'withdrawal_processed',
        'withdrawal_rejected',
        'order_refunded',
        'balance_adjusted',
        'role_changed',
        'ticket_reply',
    ];

    /**
     * Send a notification across all applicable channels.
     *
     * @param  string  $type  Notification type key (matches template name in config)
     * @param  array  $data  Additional data (stored in notification + used for template params)
     */
    public static function notify(
        int $userId,
        string $type,
        string $title,
        string $body,
        array $data = [],
    ): Notification {
        // 1) Always create in-app notification
        $notification = Notification::send($userId, $type, $title, $body, $data);

        // 2) Determine user's channel preferences
        $user = User::find($userId);
        if (! $user) {
            return $notification;
        }

        $prefs = $user->notification_prefs ?? [
            'email' => true,
            'whatsapp' => true,
        ];

        // 3) Dispatch WhatsApp (queued) — uses template if available
        if (
            in_array($type, self::WHATSAPP_TYPES, true)
            && ($prefs['whatsapp'] ?? true)
            && ! empty($user->whatsapp_number)
        ) {
            $templateParams = self::buildTemplateParams($user, $type, $data);

            SendWhatsAppNotification::dispatch(
                $user->whatsapp_number,
                $type,                    // template name
                $title,
                $body,
                $templateParams,
                $user->locale,
            )->onQueue('notifications');
        }

        // 4) Dispatch Email (queued)
        if (
            in_array($type, self::EMAIL_TYPES, true)
            && ($prefs['email'] ?? true)
        ) {
            SendEmailNotification::dispatch(
                $user->email,
                $user->name,
                $title,
                $body,
                $type,
                $data,
                $user->locale,
            )
                ->onQueue('notifications');
        }

        return $notification;
    }

    /**
     * Notify every active admin (in-app always; WhatsApp/email per the same
     * WHATSAPP_TYPES/EMAIL_TYPES rules as notify()). Used for platform-wide
     * operational events an admin should stay in the loop on — new
     * registrations, new orders, orders finishing without anyone clicking
     * anything (the scheduled sync resolving a stuck/completed order).
     */
    public static function notifyAdmins(string $type, string $title, string $body, array $data = []): void
    {
        User::where('role', 'admin')
            ->where('is_active', true)
            ->pluck('id')
            ->each(fn (int $adminId) => self::notify($adminId, $type, $title, $body, $data));
    }

    /**
     * Send a welcome WhatsApp to a newly-registered user.
     */
    public static function sendWelcome(User $user): void
    {
        if (empty($user->whatsapp_number)) {
            return;
        }

        // In-app
        Notification::send(
            $user->id,
            'welcome',
            __('mail.templates.welcome.subject', [], $user->locale ?? 'en'),
            __('mail.templates.welcome.body', ['name' => $user->name], $user->locale ?? 'en'),
        );

        SendEmailNotification::dispatch(
            (string) $user->email,
            (string) $user->name,
            __('mail.templates.welcome.subject', [], $user->locale ?? 'en'),
            __('mail.templates.welcome.body', ['name' => $user->name], $user->locale ?? 'en'),
            'welcome',
            ['name' => $user->name],
            $user->locale,
        )->onQueue('notifications');

        // WhatsApp (template-based)
        SendWhatsAppNotification::dispatch(
            $user->whatsapp_number,
            'welcome_message',
            __('mail.templates.welcome.subject', [], $user->locale ?? 'en'),
            __('mail.templates.welcome.body', ['name' => $user->name], $user->locale ?? 'en'),
            [$user->name],
            $user->locale,
        )->onQueue('notifications');
    }

    /**
     * Map notification type + data to ordered template parameter values.
     */
    private static function buildTemplateParams(User $user, string $type, array $data): array
    {
        return match ($type) {
            'deposit_confirmed' => [
                $user->name,
                $data['amount'] ?? '—',
                '$'.number_format((float) $user->balance, 2),
                now()->format('M j, Y'),
            ],
            'deposit_rejected' => [
                $user->name,
                $data['amount'] ?? '—',
            ],
            'withdrawal_processed' => [
                $user->name,
                $data['amount'] ?? '—',
                now()->format('M j, Y'),
            ],
            'withdrawal_rejected' => [
                $user->name,
                $data['refund_amount'] ?? $data['amount'] ?? '—',
            ],
            'order_status_changed' => [
                $user->name,
                (string) ($data['order_id'] ?? ''),
                $data['status'] ?? '—',
                $data['service_name'] ?? '—',
                (string) ($data['quantity'] ?? ''),
            ],
            'order_refunded' => [
                $user->name,
                (string) ($data['order_id'] ?? ''),
                $data['refund_amount'] ?? '—',
                $data['amount'] ?? '—',
            ],
            'balance_adjusted' => [
                $user->name,
                $data['adjustment'] ?? $data['amount'] ?? '—',
                $data['reason'] ?? '—',
                '$'.number_format((float) $user->balance, 2),
            ],
            'role_changed' => [
                $user->name,
                $data['new_role'] ?? $user->role,
            ],
            'contract_application' => [
                $user->name,
                $data['contract_title'] ?? '—',
                $data['applicant_name'] ?? '—',
            ],
            'ticket_reply' => [
                $user->name,
                $data['ticket_subject'] ?? '—',
            ],
            'admin_new_registration' => [
                $data['user_name'] ?? '—',
                $data['user_email'] ?? '—',
                $data['role'] ?? '—',
            ],
            'admin_new_order' => [
                (string) ($data['order_id'] ?? ''),
                $data['user_name'] ?? '—',
                $data['service_name'] ?? '—',
                $data['amount'] ?? '—',
            ],
            default => [$user->name],
        };
    }
}
