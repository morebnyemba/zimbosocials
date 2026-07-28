<?php

namespace Tests\Feature;

use App\WhatsApp\Messaging\Responder;
use App\WhatsApp\Messaging\WhatsAppGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Sending pictures, videos, audio and documents out to a customer.
 *
 * These are FREE-FORM messages: they only reach someone inside the 24-hour
 * customer service window (WhatsAppAccount::serviceWindowOpen). Outside it,
 * media has to ride a template with a media header.
 */
class WhatsAppOutboundMediaTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '263771234567';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.whatsapp.api_token' => 't',
            'services.whatsapp.phone_number_id' => '1',
            'services.whatsapp.graph_version' => 'v21.0',
        ]);
    }

    /** @return array the JSON body of the last Meta call */
    private function lastPayload(): array
    {
        $sent = Http::recorded();

        return json_decode($sent[count($sent) - 1][0]->body(), true) ?? [];
    }

    public function test_an_image_is_sent_by_public_link_with_a_caption(): void
    {
        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.img']]])]);

        $res = app(WhatsAppGateway::class)->sendMedia(
            self::PHONE, 'image', 'https://cdn.example.com/promo.jpg', 'Our new packages 🔥'
        );

        $this->assertTrue($res['ok']);
        $p = $this->lastPayload();
        $this->assertSame('image', $p['type']);
        $this->assertSame('https://cdn.example.com/promo.jpg', $p['image']['link']);
        $this->assertSame('Our new packages 🔥', $p['image']['caption']);
    }

    public function test_a_video_can_be_sent(): void
    {
        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.vid']]])]);

        app(WhatsAppGateway::class)->sendMedia(self::PHONE, 'video', 'https://cdn.example.com/advert.mp4', 'Your advert');

        $p = $this->lastPayload();
        $this->assertSame('video', $p['type']);
        $this->assertSame('https://cdn.example.com/advert.mp4', $p['video']['link']);
    }

    public function test_audio_never_carries_a_caption(): void
    {
        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.aud']]])]);

        // WhatsApp silently drops a caption on audio — don't pretend it sent one.
        app(WhatsAppGateway::class)->sendMedia(self::PHONE, 'audio', 'https://cdn.example.com/voice.ogg', 'ignored');

        $p = $this->lastPayload();
        $this->assertSame('audio', $p['type']);
        $this->assertArrayNotHasKey('caption', $p['audio']);
    }

    public function test_an_uploaded_media_id_is_sent_by_id_not_link(): void
    {
        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.x']]])]);

        app(WhatsAppGateway::class)->sendMedia(self::PHONE, 'image', '1234567890', 'hi');

        $p = $this->lastPayload();
        $this->assertSame('1234567890', $p['image']['id']);
        $this->assertArrayNotHasKey('link', $p['image']);
    }

    public function test_a_document_carries_its_filename(): void
    {
        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.doc']]])]);

        app(WhatsAppGateway::class)->sendMedia(
            self::PHONE, 'document', 'https://cdn.example.com/receipt.pdf', 'Your receipt', 'receipt.pdf'
        );

        $p = $this->lastPayload();
        $this->assertSame('receipt.pdf', $p['document']['filename']);
    }

    public function test_an_unsupported_kind_is_rejected_without_calling_meta(): void
    {
        Http::fake();

        $res = app(WhatsAppGateway::class)->sendMedia(self::PHONE, 'hologram', 'https://x/y.bin');

        $this->assertFalse($res['ok']);
        Http::assertNothingSent();
    }

    public function test_sent_media_is_recorded_in_the_transcript(): void
    {
        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.img']]])]);

        $ok = app(Responder::class)->sendMedia(
            self::PHONE, 'image', 'https://cdn.example.com/promo.jpg', 'Our packages'
        );

        $this->assertTrue($ok);
        $out = \App\Models\WhatsAppMessage::where('wa_phone', self::PHONE)->latest('id')->first();
        $this->assertSame('image', $out->msg_type);
        $this->assertSame('out', $out->direction);
        $this->assertSame('Our packages', $out->body);
        $this->assertSame('wamid.img', $out->wa_message_id);
    }

    public function test_a_failed_media_send_reports_false_so_callers_can_fall_back(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'bad media']], 400)]);

        $ok = app(Responder::class)->sendMedia(self::PHONE, 'image', 'https://cdn.example.com/broken.jpg');

        $this->assertFalse($ok, 'the caller must be able to fall back to text');
        $this->assertDatabaseCount('whatsapp_messages', 0);
    }

    public function test_uploading_bytes_returns_a_reusable_media_id(): void
    {
        Http::fake(['*' => Http::response(['id' => '9876543210'])]);

        $res = app(WhatsAppGateway::class)->uploadMedia('JPEGBYTES', 'image/jpeg', 'promo.jpg', 'image');

        $this->assertTrue($res['ok']);
        $this->assertSame('9876543210', $res['media_id']);
    }

    public function test_an_oversized_upload_is_refused_before_the_network_call(): void
    {
        Http::fake();

        // Meta caps images at 5 MB.
        $res = app(WhatsAppGateway::class)->uploadMedia(str_repeat('x', 6 * 1024 * 1024), 'image/jpeg', 'big.jpg', 'image');

        $this->assertFalse($res['ok']);
        $this->assertSame('too_large', $res['reason']);
        Http::assertNothingSent();
    }
}
