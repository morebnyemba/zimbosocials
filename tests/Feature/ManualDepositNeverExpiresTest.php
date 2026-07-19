<?php

namespace Tests\Feature;

use App\Models\ManualPaymentDetail;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessage;
use App\Services\DepositService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * A manual deposit means the customer sent real money to our account. It is
 * settled by a human, so it must never be auto-expired — and they must never be
 * told "no money was taken", which would be false and reads like we kept it.
 *
 * Seen in production: a $100 manual EcoCash deposit (proof submitted) was
 * auto-expired a day later and the customer was told it failed.
 */
class ManualDepositNeverExpiresTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '263771234567';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.whatsapp.api_token' => 't', 'services.whatsapp.phone_number_id' => '1']);
        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.x']]])]);

        ManualPaymentDetail::create([
            'method_key' => 'manual_ecocash', 'label' => 'Ecocash Manual',
            'account_name' => 'Z', 'account_number' => '077', 'is_active' => true, 'sort_order' => 1,
        ]);
    }

    private function linkedUser(): User
    {
        $user = User::factory()->create(['balance' => 0]);
        WhatsAppAccount::create([
            'wa_phone' => self::PHONE, 'user_id' => $user->id,
            'link_status' => 'linked', 'opted_in' => true,
        ]);

        return $user;
    }

    private function staleDeposit(User $user, string $method, ?string $proof = null): Transaction
    {
        $tx = Transaction::create([
            'user_id' => $user->id, 'type' => 'deposit', 'amount' => 100.0,
            'balance_before' => 0, 'balance_after' => 0,
            'method' => $method, 'status' => 'pending', 'proof_url' => $proof,
            'notes' => 'awaiting proof',
        ]);
        $tx->forceFill(['created_at' => now()->subHours(30)])->save();

        return $tx;
    }

    public function test_a_stale_manual_deposit_is_left_for_admin_review(): void
    {
        $user = $this->linkedUser();
        $tx = $this->staleDeposit($user, 'manual_ecocash'); // no proof yet

        $this->artisan('transactions:cleanup-stale --hours=24')->assertSuccessful();

        $this->assertSame('pending', $tx->fresh()->status, 'a manual deposit must never be auto-expired');
    }

    public function test_a_manual_deposit_with_proof_is_also_left_alone(): void
    {
        $user = $this->linkedUser();
        $tx = $this->staleDeposit($user, 'manual_ecocash', '/storage/proofs/1/x.jpg');

        $this->artisan('transactions:cleanup-stale --hours=24')->assertSuccessful();

        $this->assertSame('pending', $tx->fresh()->status);
    }

    public function test_a_stale_gateway_deposit_is_still_expired(): void
    {
        $user = $this->linkedUser();
        $tx = $this->staleDeposit($user, 'ecocash'); // Paynow express, no poll URL

        $this->artisan('transactions:cleanup-stale --hours=24')->assertSuccessful();

        $this->assertSame('expired', $tx->fresh()->status);
    }

    public function test_a_rejected_manual_deposit_never_claims_no_money_was_taken(): void
    {
        $user = $this->linkedUser();
        $tx = $this->staleDeposit($user, 'manual_ecocash');

        // An admin can still reject one (e.g. unreadable proof).
        app(DepositService::class)->reject($tx, 'admin');

        $body = (string) WhatsAppMessage::where('direction', 'out')->latest('id')->first()?->body;
        $this->assertStringNotContainsString('No money was taken', $body);
        $this->assertStringContainsString('proof of payment', $body);
    }

    public function test_a_failed_gateway_deposit_still_reassures_that_nothing_was_taken(): void
    {
        $user = $this->linkedUser();
        $tx = $this->staleDeposit($user, 'ecocash');

        app(DepositService::class)->reject($tx, 'gateway_poller');

        $body = (string) WhatsAppMessage::where('direction', 'out')->latest('id')->first()?->body;
        $this->assertStringContainsString('No money was taken', $body);
    }
}
