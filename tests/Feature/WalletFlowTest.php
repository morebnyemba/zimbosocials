<?php

namespace Tests\Feature;

use App\Models\ManualPaymentDetail;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WalletFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_page_loads_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/wallet');

        $response->assertStatus(200);
    }

    public function test_manual_deposit_creates_pending_transaction_for_manual_method(): void
    {
        $user = User::factory()->create([
            'balance' => 100,
        ]);

        ManualPaymentDetail::create([
            'method_key' => 'innbucks',
            'label' => 'InnBucks',
            'is_active' => true,
            'sort_order' => 1,
            'gateway_type' => null,
        ]);

        $response = $this->actingAs($user)->post('/wallet/add-funds', [
            'amount' => 25,
            'method' => 'innbucks',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type' => 'deposit',
            'status' => 'pending',
            'method' => 'innbucks',
        ]);
    }

    public function test_proof_submission_rejects_invalid_file_type(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'type' => 'deposit',
            'amount' => 10,
            'balance_before' => 0,
            'balance_after' => 0,
            'method' => 'innbucks',
            'status' => 'pending',
            'notes' => 'Awaiting proof',
        ]);

        $file = UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf');

        $response = $this->from('/wallet')->actingAs($user)->post('/wallet/submit-proof', [
            'transaction_id' => $transaction->getKey(),
            'proof_file' => $file,
        ]);

        $response->assertRedirect('/wallet');
        $response->assertSessionHasErrors(['proof_file']);
    }

    public function test_valid_proof_submission_updates_transaction(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'type' => 'deposit',
            'amount' => 10,
            'balance_before' => 0,
            'balance_after' => 0,
            'method' => 'innbucks',
            'status' => 'pending',
            'notes' => 'Awaiting proof',
        ]);

        $tmpPng = tempnam(sys_get_temp_dir(), 'proof_').'.png';
        file_put_contents(
            $tmpPng,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO5X2wAAAABJRU5ErkJggg==')
        );

        $image = new UploadedFile(
            $tmpPng,
            'proof.png',
            'image/png',
            null,
            true
        );

        $response = $this->actingAs($user)->post('/wallet/submit-proof', [
            'transaction_id' => $transaction->getKey(),
            'proof_file' => $image,
        ]);

        $response->assertRedirect();

        $transaction->refresh();

        $this->assertNotNull($transaction->getAttribute('proof_url'));
        $this->assertStringContainsString('/storage/proofs/'.$user->id.'/', (string) $transaction->getAttribute('proof_url'));
        $this->assertSame('pending', (string) $transaction->getAttribute('status'));

        @unlink($tmpPng);
    }

    public function test_gateway_deposit_cannot_submit_proof(): void
    {
        $user = User::factory()->create();

        // A Paynow deposit carries a poll-URL reference — no proof needed.
        $transaction = Transaction::create([
            'user_id' => $user->id, 'type' => 'deposit', 'amount' => 20,
            'balance_before' => 0, 'balance_after' => 20, 'method' => 'ecocash',
            'status' => 'pending', 'reference' => 'https://www.paynow.co.zw/interface/poll/?guid=abc',
        ]);

        $tmpPng = tempnam(sys_get_temp_dir(), 'proof_').'.png';
        file_put_contents($tmpPng, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO5X2wAAAABJRU5ErkJggg=='));

        $this->actingAs($user)->post('/wallet/submit-proof', [
            'transaction_id' => $transaction->getKey(),
            'proof_file' => new UploadedFile($tmpPng, 'proof.png', 'image/png', null, true),
        ])->assertSessionHas('error');

        $this->assertNull($transaction->fresh()->getAttribute('proof_url'));
        @unlink($tmpPng);
    }

    public function test_paynow_init_rejects_non_gateway_method(): void
    {
        $user = User::factory()->create();

        ManualPaymentDetail::create([
            'method_key' => 'paynow',
            'label' => 'Paynow',
            'is_active' => true,
            'sort_order' => 1,
            'gateway_type' => 'paynow',
        ]);

        ManualPaymentDetail::create([
            'method_key' => 'innbucks',
            'label' => 'InnBucks',
            'is_active' => true,
            'sort_order' => 2,
            'gateway_type' => null,
        ]);

        $response = $this->from('/wallet')->actingAs($user)->post('/paynow/init', [
            'amount' => 5,
            'method' => 'innbucks',
        ]);

        // The controller answers JSON (the wallet UI calls this via fetch):
        // non-gateway methods are rejected with a 422 payload, not a redirect.
        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }
}
