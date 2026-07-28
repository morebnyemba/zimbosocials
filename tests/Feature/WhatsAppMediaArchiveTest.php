<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessage;
use App\WhatsApp\Routing\MessageRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Inbound media has to be archived the moment it lands: Meta's media links are
 * short-lived and need the bearer token, and the media id itself expires — so
 * without a local copy a customer's screenshot or voice note is unviewable
 * minutes later, and the console can only show "[image]".
 */
class WhatsAppMediaArchiveTest extends TestCase
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
            // Keep the AI out of it — this is about storage, not understanding.
            'services.whatsapp.media_ai' => false,
        ]);
        Storage::fake('public');
    }

    private function fakeDownload(string $bytes = 'IMAGEBYTES', string $mime = 'image/jpeg'): void
    {
        Http::fake([
            'lookaside.fbcdn.net/*' => Http::response($bytes, 200, ['Content-Type' => $mime]),
            'graph.facebook.com/*/messages' => Http::response(['messages' => [['id' => 'wamid.out']]]),
            'graph.facebook.com/*' => Http::response(['url' => 'https://lookaside.fbcdn.net/x', 'mime_type' => $mime, 'file_size' => strlen($bytes)]),
        ]);
    }

    private function mediaMsg(string $mime = 'image/jpeg', string $kind = 'image'): array
    {
        return [
            'from' => self::PHONE,
            'wa_message_id' => 'wamid.'.uniqid('', true),
            'type' => $kind,
            'text' => '',
            'interactive_id' => null,
            'media' => ['id' => 'MEDIA1', 'mime' => $mime, 'kind' => $kind],
            'name' => 'Tester',
            'timestamp' => time(),
            'raw' => [],
        ];
    }

    public function test_an_inbound_photo_is_stored_and_linked_to_the_message(): void
    {
        $user = User::factory()->create();
        WhatsAppAccount::create([
            'wa_phone' => self::PHONE, 'user_id' => $user->id,
            'link_status' => 'linked', 'opted_in' => true,
        ]);
        $this->fakeDownload();

        app(MessageRouter::class)->handle($this->mediaMsg());

        $in = WhatsAppMessage::where('direction', 'in')->latest('id')->first();
        $this->assertNotNull($in->media_url, 'the photo must be archived locally');
        $this->assertSame('image/jpeg', $in->media_mime);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $in->media_url));
    }

    public function test_a_voice_note_is_archived_so_it_can_be_played_back(): void
    {
        $user = User::factory()->create();
        WhatsAppAccount::create([
            'wa_phone' => self::PHONE, 'user_id' => $user->id,
            'link_status' => 'linked', 'opted_in' => true,
        ]);
        $this->fakeDownload('OGGBYTES', 'audio/ogg');

        app(MessageRouter::class)->handle($this->mediaMsg('audio/ogg', 'audio'));

        $in = WhatsAppMessage::where('direction', 'in')->latest('id')->first();
        $this->assertSame('audio/ogg', $in->media_mime);
        $this->assertStringEndsWith('.ogg', (string) $in->media_url);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $in->media_url));
    }

    public function test_media_from_an_unknown_guest_is_still_archived(): void
    {
        // No linked account — the reply differs, but we must not lose the file.
        $this->fakeDownload();

        app(MessageRouter::class)->handle($this->mediaMsg());

        $in = WhatsAppMessage::where('direction', 'in')->latest('id')->first();
        $this->assertNotNull($in->media_url);
    }

    public function test_the_media_is_downloaded_only_once_per_message(): void
    {
        $user = User::factory()->create();
        WhatsAppAccount::create([
            'wa_phone' => self::PHONE, 'user_id' => $user->id,
            'link_status' => 'linked', 'opted_in' => true,
        ]);
        $this->fakeDownload();

        app(MessageRouter::class)->handle($this->mediaMsg());

        // One id->url lookup + one byte fetch. A second download would mean the
        // proof/AI paths are re-fetching what the archive already has.
        $binFetches = collect(Http::recorded())
            ->filter(fn ($r) => str_contains($r[0]->url(), 'lookaside.fbcdn.net'))
            ->count();
        $this->assertSame(1, $binFetches, 'media should be fetched once, not once per consumer');
    }

    public function test_a_failed_download_never_breaks_the_message(): void
    {
        $user = User::factory()->create();
        WhatsAppAccount::create([
            'wa_phone' => self::PHONE, 'user_id' => $user->id,
            'link_status' => 'linked', 'opted_in' => true,
        ]);
        Http::fake([
            'graph.facebook.com/*/messages' => Http::response(['messages' => [['id' => 'wamid.out']]]),
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'gone']], 404),
        ]);

        app(MessageRouter::class)->handle($this->mediaMsg());

        // Archiving is best-effort: the message is still recorded, just without
        // a local copy, and the customer still got a reply.
        $in = WhatsAppMessage::where('direction', 'in')->latest('id')->first();
        $this->assertNotNull($in);
        $this->assertNull($in->media_url);
    }

    public function test_the_console_exposes_the_media_url_for_playback(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $account = WhatsAppAccount::create([
            'wa_phone' => self::PHONE, 'link_status' => 'guest', 'opted_in' => true,
        ]);
        WhatsAppMessage::create([
            'wa_phone' => self::PHONE, 'direction' => 'in', 'msg_type' => 'audio',
            'body' => null, 'media_url' => '/storage/wa-media/x/y.ogg', 'media_mime' => 'audio/ogg',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.whatsapp.conversation', $account->id))
            ->assertInertia(fn ($p) => $p
                ->component('Admin/WhatsApp/Conversation')
                ->where('messages.0.media_url', '/storage/wa-media/x/y.ogg')
                ->where('messages.0.media_mime', 'audio/ogg')
            );
    }
}
