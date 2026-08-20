<?php

namespace Tests\Feature;

use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessage;
use App\WhatsApp\Routing\MessageRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A contact can tell the bot when THEY want the next automated follow-up
 * ("remind me next week", "stop following up for now") instead of the
 * automated systems (nudges, saved-order reminders, marketing broadcasts)
 * guessing a fixed cadence. The request is recognised deterministically,
 * before flows/AI, and the resulting schedule is enforced by
 * WhatsAppAccount::canReceiveAutomatedMessage() — the same gate every
 * automated sender already checks.
 */
class WhatsAppFollowUpSchedulingTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '263771234567';

    private function account(): WhatsAppAccount
    {
        return WhatsAppAccount::create([
            'wa_phone' => self::PHONE, 'link_status' => 'guest', 'opted_in' => true,
        ]);
    }

    private function textMessage(string $text): array
    {
        return [
            'from' => self::PHONE,
            'wa_message_id' => 'wamid.'.uniqid('', true),
            'type' => 'text',
            'text' => $text,
            'interactive_id' => null,
            'media' => null,
            'name' => 'Tester',
            'timestamp' => time(),
            'raw' => [],
        ];
    }

    public function test_asking_to_be_reminded_next_week_schedules_it_and_confirms(): void
    {
        $account = $this->account();

        app(MessageRouter::class)->handle($this->textMessage('can you remind me next week?'));

        $fresh = $account->fresh();
        $this->assertNotNull($fresh->follow_up_snooze_until);
        $this->assertTrue($fresh->follow_up_snooze_until->between(now()->addDays(6), now()->addDays(8)));

        $out = WhatsAppMessage::where('direction', 'out')->latest('id')->first();
        $this->assertStringContainsString('in a week', (string) $out->body);
        $this->assertSame('followup_snooze', $out->intent);
    }

    public function test_a_snoozed_contact_cannot_receive_automated_messages_until_it_passes(): void
    {
        $account = $this->account();
        $account->snoozeFollowUpsUntil(now()->addDays(3));

        $this->assertFalse($account->fresh()->canReceiveAutomatedMessage());
    }

    public function test_once_the_snooze_passes_automated_messages_resume(): void
    {
        $account = $this->account();
        $account->snoozeFollowUpsUntil(now()->subMinute());

        $this->assertTrue($account->fresh()->canReceiveAutomatedMessage());
    }

    public function test_stopping_follow_ups_without_a_duration_pauses_for_a_long_while(): void
    {
        $this->account();

        app(MessageRouter::class)->handle($this->textMessage('please stop following up for now'));

        $out = WhatsAppMessage::where('direction', 'out')->latest('id')->first();
        $this->assertSame('followup_snooze', $out->intent);

        $account = WhatsAppAccount::where('wa_phone', self::PHONE)->first();
        $this->assertTrue($account->follow_up_snooze_until->greaterThan(now()->addDays(150)));
    }

    public function test_unrelated_text_never_triggers_scheduling(): void
    {
        $this->account();

        app(MessageRouter::class)->handle($this->textMessage("I'll pay tomorrow"));

        $account = WhatsAppAccount::where('wa_phone', self::PHONE)->first();
        $this->assertNull($account->follow_up_snooze_until);
    }
}
