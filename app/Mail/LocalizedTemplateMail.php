<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LocalizedTemplateMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $subjectLine,
        public readonly string $template,
        public readonly array $payload,
        string $locale,
    ) {
        // Mailable already declares a public $locale property; assign to it
        // (it cannot be redeclared as a readonly promoted property).
        $this->locale = $locale;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.localized-template',
            with: [
                'template' => $this->template,
                'payload' => $this->payload,
                'locale' => $this->locale,
            ],
        );
    }
}
