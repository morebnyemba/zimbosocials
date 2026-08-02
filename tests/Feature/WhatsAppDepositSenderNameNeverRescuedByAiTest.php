<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessage;
use App\Services\AI\GeminiClient;
use App\WhatsApp\Routing\MessageRouter;
use App\WhatsApp\Session\SessionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Seen live: a customer replied "#46" (an order number) instead of a name at
 * the deposit flow's awaiting_sender_name step. That failed validation, the
 * AI "rescued" it by answering about order #46 instead of re-asking, and the
 * customer's NEXT message ("Ndikuda ma followers pliz" — not a name either)
 * was what actually got captured as the sender name an admin later matches
 * against a real bank statement. Garbage here doesn't just read badly, it
 * corrupts money verification — same reasoning as registration.
 */
class WhatsAppDepositSenderNameNeverRescuedByAiTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '263771234567';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.whatsapp.api_token' => 't',
            'services.whatsapp.phone_number_id' => '1',
            'services.gemini.api_key' => 'k',
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

    public function test_an_order_number_typed_as_a_name_never_reaches_the_ai(): void
    {
        $user = User::factory()->create(['balance' => 0]);
        WhatsAppAccount::create([
            'wa_phone' => self::PHONE, 'user_id' => $user->id,
            'link_status' => 'linked', 'opted_in' => true,
        ]);
        $tx = Transaction::create([
            'user_id' => $user->id, 'type' => 'deposit', 'amount' => 2.0,
            'balance_before' => 0, 'balance_after' => 0,
            'method' => 'manual_ecocash', 'status' => 'pending', 'notes' => 'awaiting proof',
        ]);

        // The AI must never even be asked — if it were, this mock would answer
        // about order #46 instead of the real validation error.
        $mock = Mockery::mock(GeminiClient::class);
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldNotReceive('generateJson');
        $this->app->instance(GeminiClient::class, $mock);

        $sessions = app(SessionManager::class);
        $ctx = $sessions->load(self::PHONE);
        $ctx->setFlow('deposit', 'awaiting_sender_name');
        $ctx->set('deposit_transaction_id', $tx->id);
        $sessions->save($ctx);

        app(MessageRouter::class)->handle($this->textMessage('#46'));

        $out = WhatsAppMessage::where('direction', 'out')->latest('id')->first();
        $this->assertStringContainsString('name', mb_strtolower((string) $out->body));

        // Still on the sender-name step, and nothing garbage was captured.
        $after = $sessions->load(self::PHONE);
        $this->assertSame('deposit', $after->flow);
        $this->assertSame('awaiting_sender_name', $after->state);
        $this->assertStringNotContainsString('#46', (string) $tx->fresh()->notes);
    }
}
