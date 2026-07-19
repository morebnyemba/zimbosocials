<?php

namespace Tests\Feature;

use App\Models\ManualPaymentDetail;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Services\AI\GeminiClient;
use App\WhatsApp\Routing\MessageRouter;
use App\WhatsApp\Webhook\InboundNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

/**
 * Photos and voice notes are real messages. Proof of payment still wins (money
 * first), but anything else is handed to Gemini to actually look at / listen to
 * rather than being met with a canned "I can't open files".
 */
class WhatsAppMediaUnderstandingTest extends TestCase
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
        Storage::fake('public');
        // Media lookup + download, then any outbound send.
        Http::fake([
            'graph.facebook.com/*' => Http::response(['url' => 'https://lookaside.fbcdn.net/x', 'mime_type' => 'image/jpeg', 'file_size' => 9]),
            'lookaside.fbcdn.net/*' => Http::response('JPEGBYTES', 200, ['Content-Type' => 'image/jpeg']),
        ]);
    }

    private function linkedAccount(): User
    {
        $user = User::factory()->create(['balance' => 0]);
        WhatsAppAccount::create([
            'wa_phone' => self::PHONE, 'user_id' => $user->id,
            'link_status' => 'linked', 'opted_in' => true,
        ]);

        return $user;
    }

    private function mediaMessage(array $media, string $caption = ''): array
    {
        return [
            'from' => self::PHONE,
            'wa_message_id' => 'wamid.'.uniqid('', true),
            'type' => $media['kind'] === 'audio' ? 'audio' : 'image',
            'text' => $caption,
            'interactive_id' => null,
            'media' => $media,
            'name' => 'Tester',
            'timestamp' => time(),
            'raw' => [],
        ];
    }

    /** Capture what the AI client is asked, and reply with a fixed decision. */
    private function expectGeminiCall(?callable $inspect = null): void
    {
        $mock = Mockery::mock(GeminiClient::class);
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('generateJson')
            ->andReturnUsing(function (...$args) use ($inspect) {
                if ($inspect) {
                    $inspect($args);
                }

                return ['reply' => 'That is a lovely shop photo!', 'follow_up' => null, 'flow' => 'none', 'flow_data' => []];
            });
        $this->app->instance(GeminiClient::class, $mock);
    }

    public function test_a_photo_with_nothing_pending_is_sent_to_the_ai(): void
    {
        $this->linkedAccount();
        $sentMedia = null;
        $this->expectGeminiCall(function (array $args) use (&$sentMedia) {
            // generateJson(prompt, temperature, schema:, system:, timeout:, media:)
            $sentMedia = $args[5] ?? null;
        });

        app(MessageRouter::class)->handle($this->mediaMessage(['id' => 'M1', 'mime' => 'image/jpeg', 'kind' => 'image']));

        $this->assertIsArray($sentMedia, 'the image bytes should reach Gemini');
        $this->assertSame('image/jpeg', $sentMedia[0]['mime']);
        $this->assertSame('JPEGBYTES', $sentMedia[0]['data']);

        $out = \App\Models\WhatsAppMessage::where('direction', 'out')->latest('id')->first();
        $this->assertStringContainsString('lovely shop photo', (string) $out->body);
    }

    public function test_proof_of_payment_still_wins_over_ai_understanding(): void
    {
        $user = $this->linkedAccount();
        ManualPaymentDetail::create([
            'method_key' => 'manual_ecocash', 'label' => 'Manual EcoCash',
            'account_name' => 'Z', 'account_number' => '077', 'is_active' => true, 'sort_order' => 1,
        ]);
        Transaction::create([
            'user_id' => $user->id, 'type' => 'deposit', 'amount' => 10.0,
            'balance_before' => 0, 'balance_after' => 0,
            'method' => 'manual_ecocash', 'status' => 'pending', 'notes' => 'awaiting proof',
        ]);

        // If the AI were consulted this would fail the expectation below.
        app(MessageRouter::class)->handle($this->mediaMessage(['id' => 'M1', 'mime' => 'image/jpeg', 'kind' => 'image']));

        $out = \App\Models\WhatsAppMessage::where('direction', 'out')->latest('id')->first();
        $this->assertStringContainsString('Proof received', (string) $out->body);
    }

    public function test_audio_can_be_switched_off_without_affecting_photos(): void
    {
        config(['services.whatsapp.audio_ai' => false]);
        $this->linkedAccount();

        app(MessageRouter::class)->handle($this->mediaMessage(['id' => 'M1', 'mime' => 'audio/ogg; codecs=opus', 'kind' => 'audio', 'voice' => true]));

        // Falls back to guidance instead of being sent to the model.
        $out = \App\Models\WhatsAppMessage::where('direction', 'out')->latest('id')->first();
        $this->assertStringNotContainsString('lovely shop photo', (string) $out->body);
    }

    public function test_normalizer_extracts_voice_notes(): void
    {
        $parsed = (new InboundNormalizer)->normalize([
            'entry' => [['changes' => [['value' => [
                'messages' => [[
                    'from' => self::PHONE, 'id' => 'wamid.1', 'type' => 'audio',
                    'audio' => ['id' => 'AUD1', 'mime_type' => 'audio/ogg; codecs=opus', 'voice' => true],
                ]],
            ]]]]],
        ]);

        $media = $parsed['messages'][0]['media'];
        $this->assertSame('AUD1', $media['id']);
        $this->assertSame('audio', $media['kind']);
        $this->assertTrue($media['voice']);
    }
}
