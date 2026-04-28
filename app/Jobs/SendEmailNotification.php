<?php

namespace App\Jobs;

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
    ) {}

    public function handle(): void
    {
        Mail::send([], [], function ($msg) {
            $msg->to($this->email, $this->name)
                ->subject($this->title)
                ->html($this->buildHtml());
        });
    }

    private function buildHtml(): string
    {
        return <<<HTML
        <div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 600px; margin: 0 auto; padding: 40px 20px;">
            <div style="background: linear-gradient(135deg, #7c3aed, #4f46e5); padding: 32px; border-radius: 16px; color: white; text-align: center; margin-bottom: 24px;">
                <h1 style="margin: 0; font-size: 20px; font-weight: 600;">SlykerTech SMM</h1>
            </div>
            <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px;">
                <h2 style="margin: 0 0 12px; font-size: 18px; color: #111827;">{$this->title}</h2>
                <p style="margin: 0; font-size: 14px; color: #6b7280; line-height: 1.6;">{$this->body}</p>
            </div>
            <p style="text-align: center; margin-top: 24px; font-size: 12px; color: #9ca3af;">
                You received this because you have an account at SlykerTech SMM. <br>
                Manage your notification preferences in Settings.
            </p>
        </div>
        HTML;
    }
}
