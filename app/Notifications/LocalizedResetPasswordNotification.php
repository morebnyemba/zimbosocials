<?php

namespace App\Notifications;

use App\Mail\LocalizedTemplateMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class LocalizedResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): LocalizedTemplateMail
    {
        $locale = in_array($notifiable->locale, ['sn', 'nd', 'en'], true) ? $notifiable->locale : 'en';
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $payload = [
            'subject' => __('mail.templates.reset_password.subject', [], $locale),
            'body' => __('mail.templates.reset_password.body', ['name' => $notifiable->name], $locale),
            'action_url' => $url,
        ];

        return new LocalizedTemplateMail(
            $payload['subject'],
            'reset_password',
            $payload,
            $locale,
        );
    }
}
