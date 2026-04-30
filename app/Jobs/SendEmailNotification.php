<?php

namespace App\Jobs;

use App\Mail\LocalizedTemplateMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendEmailNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 60;

    public function __construct(
        public readonly string $email,
        public readonly string $name,
        public readonly string $title,
        public readonly string $body,
        public readonly string $template = 'generic_notification',
        public readonly array $payload = [],
        public readonly ?string $locale = null,
    ) {}

    public function handle(): void
    {
        $resolvedLocale = in_array($this->locale, ['sn', 'nd', 'en'], true) ? $this->locale : 'en';
        $mail = new LocalizedTemplateMail(
            $this->title,
            $this->template,
            array_merge([
                'subject' => $this->title,
                'body' => $this->body,
                'name' => $this->name,
            ], $this->payload),
            $resolvedLocale,
        );

        Mail::to($this->email, $this->name)->send($mail);
    }
}
