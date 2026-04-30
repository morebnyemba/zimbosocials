<?php

namespace App\Notifications;

use App\Mail\LocalizedTemplateMail;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Queue\ShouldQueue;

class LocalizedVerifyEmailNotification extends VerifyEmail implements ShouldQueue
{
    public function toMail($notifiable): LocalizedTemplateMail
    {
        $locale = in_array($notifiable->locale, ['sn', 'nd', 'en'], true) ? $notifiable->locale : 'en';
        $verificationUrl = $this->verificationUrl($notifiable);

        $payload = [
            'subject' => __('mail.templates.verify_email.subject', [], $locale),
            'body' => __('mail.templates.verify_email.body', ['name' => $notifiable->name], $locale),
            'action_url' => $verificationUrl,
        ];

        return new LocalizedTemplateMail(
            $payload['subject'],
            'verify_email',
            $payload,
            $locale,
        );
    }
}
