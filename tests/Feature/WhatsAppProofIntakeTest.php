<?php

namespace Tests\Feature;

use App\Models\ManualPaymentDetail;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\WhatsApp\Deposit\ProofIntake;
use App\WhatsApp\Routing\MessageRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WhatsAppProofIntakeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.whatsapp.api_token' => 'test-token',
            'services.whatsapp.phone_number_id' => '123',
            'services.whatsapp.graph_version' => 'v21.0',
        ]);
        Storage::fake('public');
    }

    private function manualMethod(string $key = 'manual_ecocash'): ManualPaymentDetail
    {
        return ManualPaymentDetail::create([
            'method_key' => $key, 'label' => 'Manual EcoCash',
            'account_name' => 'ZimboSocials', 'account_number' => '0771234567',
            'instructions' => 'ref your username', 'is_active' => true, 'sort_order' => 1,
        ]);
    }

    private function pendingManual(User $user, string $method = 'manual_ecocash'): Transaction
    {
        return Transaction::create([
            'user_id' => $user->id, 'type' => 'deposit', 'amount' => 10.0,
            'balance_before' => $user->balance, 'balance_after' => $user->balance,
            'method' => $method, 'status' => 'pending',
            'notes' => 'Awaiting proof of payment (via WhatsApp)',
        ]);
    }

    private function fakeMediaDownload(string $bytes = 'JPEGBYTES', string $mime = 'image/jpeg'): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['url' => 'https://lookaside.fbcdn.net/media/xyz', 'mime_type' => $mime, 'file_size' => strlen($bytes)]),
            'lookaside.fbcdn.net/*' => Http::response($bytes, 200, ['Content-Type' => $mime]),
        ]);
    }

    public function test_image_attaches_as_proof_to_the_pending_manual_deposit(): void
    {
        $this->manualMethod();
        $user = User::factory()->create(['balance' => 0]);
        $tx = $this->pendingManual($user);
        $this->fakeMediaDownload();

        $res = app(ProofIntake::class)->intake($user, ['id' => 'MEDIA1', 'mime' => 'image/jpeg', 'kind' => 'image']);

        $this->assertTrue($res['ok']);
        $tx->refresh();
        $this->assertNotNull($tx->proof_url);
        $this->assertStringContainsString('/storage/proofs/'.$user->id.'/', $tx->proof_url);
        $this->assertStringContainsString('WhatsApp', (string) $tx->notes);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $tx->proof_url));
        // Still pending — an admin credits it after verifying.
        $this->assertSame('pending', $tx->fresh()->status);
    }

    public function test_no_pending_manual_deposit_is_reported(): void
    {
        $user = User::factory()->create(['balance' => 0]);
        $this->fakeMediaDownload();

        $res = app(ProofIntake::class)->intake($user, ['id' => 'MEDIA1', 'mime' => 'image/jpeg', 'kind' => 'image']);

        $this->assertFalse($res['ok']);
        $this->assertSame('no_pending', $res['reason']);
    }

    public function test_gateway_deposit_never_accepts_proof(): void
    {
        $user = User::factory()->create(['balance' => 0]);
        // An express EcoCash deposit is gateway (auto-confirm) — not manual.
        $this->pendingManual($user, method: 'ecocash');
        $this->fakeMediaDownload();

        $res = app(ProofIntake::class)->intake($user, ['id' => 'MEDIA1', 'mime' => 'image/jpeg', 'kind' => 'image']);

        $this->assertFalse($res['ok']);
        $this->assertSame('no_pending', $res['reason']);
    }

    public function test_unsupported_file_type_is_rejected(): void
    {
        $this->manualMethod();
        $user = User::factory()->create(['balance' => 0]);
        $this->pendingManual($user);

        $res = app(ProofIntake::class)->intake($user, ['id' => 'MEDIA1', 'mime' => 'audio/ogg', 'kind' => 'document']);

        $this->assertFalse($res['ok']);
        $this->assertSame('bad_type', $res['reason']);
    }

    public function test_router_routes_an_inbound_image_to_proof_intake(): void
    {
        $this->manualMethod();
        $user = User::factory()->create(['balance' => 0]);
        WhatsAppAccount::create([
            'wa_phone' => '263771234567', 'user_id' => $user->id,
            'link_status' => 'linked', 'opted_in' => true,
        ]);
        $tx = $this->pendingManual($user);
        $this->fakeMediaDownload();

        // Mock the responder so no real WhatsApp send is attempted; we only care
        // that the image routed to proof intake and a confirmation was drafted.
        $responder = \Mockery::spy(\App\WhatsApp\Messaging\Responder::class);
        $this->app->instance(\App\WhatsApp\Messaging\Responder::class, $responder);

        $mediaMsg = [
            'from' => '263771234567',
            'wa_message_id' => 'wamid.'.uniqid('', true),
            'type' => 'image',
            'text' => '',
            'interactive_id' => null,
            'media' => ['id' => 'MEDIA1', 'mime' => 'image/jpeg', 'kind' => 'image'],
            'name' => 'Tester',
            'timestamp' => time(),
            'raw' => [],
        ];

        app(MessageRouter::class)->handle($mediaMsg);

        $this->assertNotNull($tx->fresh()->proof_url);
        $responder->shouldHaveReceived('send')
            ->withArgs(fn ($phone, $body, $meta = []) => str_contains((string) $body, 'Proof received'));
    }

    public function test_pdf_receipt_is_accepted(): void
    {
        $this->manualMethod();
        $user = User::factory()->create(['balance' => 0]);
        $tx = $this->pendingManual($user);
        $this->fakeMediaDownload('%PDF-1.4 fake', 'application/pdf');

        $res = app(ProofIntake::class)->intake($user, ['id' => 'MEDIA1', 'mime' => 'application/pdf', 'kind' => 'document', 'filename' => 'receipt.pdf']);

        $this->assertTrue($res['ok']);
        $this->assertStringEndsWith('.pdf', (string) $tx->fresh()->proof_url);
    }
}
