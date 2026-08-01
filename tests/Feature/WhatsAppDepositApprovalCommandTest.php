<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessage;
use App\WhatsApp\Routing\MessageRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The only WhatsApp path that moves money: "APPROVE 161" (or a bare
 * "approve"/"received" swipe-replied to the alert) credits a pending manual
 * deposit. Every guard here exists because a customer's plain "received" —
 * which has never been a parsed command, just a window-keepalive — must
 * never be mistaken for this, and a typo'd id must never credit the wrong
 * deposit.
 */
class WhatsAppDepositApprovalCommandTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_PHONE = '263771111111';

    private const CUSTOMER_PHONE = '263772222222';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.whatsapp.api_token' => 't', 'services.whatsapp.phone_number_id' => '1']);
    }

    private function adminAccount(): User
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        WhatsAppAccount::create([
            'wa_phone' => self::ADMIN_PHONE, 'user_id' => $admin->id,
            'link_status' => 'linked', 'opted_in' => true,
        ]);

        return $admin;
    }

    private function pendingDeposit(float $balance = 0): Transaction
    {
        $customer = User::factory()->create(['balance' => $balance]);

        return Transaction::create([
            'user_id' => $customer->id, 'type' => 'deposit', 'amount' => 10.0,
            'balance_before' => $balance, 'balance_after' => $balance,
            'method' => 'manual_ecocash', 'status' => 'pending', 'notes' => 'awaiting proof',
        ]);
    }

    private function message(string $text, string $from, ?string $quotedId = null): array
    {
        return [
            'from' => $from,
            'wa_message_id' => 'wamid.'.uniqid('', true),
            'type' => 'text',
            'text' => $text,
            'interactive_id' => null,
            'media' => null,
            'quoted_wa_message_id' => $quotedId,
            'name' => 'Tester',
            'timestamp' => time(),
            'raw' => [],
        ];
    }

    public function test_approve_with_an_id_credits_the_deposit(): void
    {
        $this->adminAccount();
        $tx = $this->pendingDeposit();

        app(MessageRouter::class)->handle($this->message("APPROVE {$tx->id}", self::ADMIN_PHONE));

        $tx->refresh();
        $this->assertSame('completed', $tx->status);
        // Reuses DepositService::credit() as-is, manual bonus included (+5%
        // default) — same path the admin panel's approve button takes.
        $this->assertSame(10.5, (float) $tx->user->fresh()->balance);

        $out = WhatsAppMessage::where('direction', 'out')->latest('id')->first();
        $this->assertStringContainsString('Approved', (string) $out->body);
    }

    public function test_received_with_a_hash_id_also_works(): void
    {
        $this->adminAccount();
        $tx = $this->pendingDeposit();

        app(MessageRouter::class)->handle($this->message("received #{$tx->id}", self::ADMIN_PHONE));

        $this->assertSame('completed', $tx->fresh()->status);
    }

    public function test_a_bare_approve_resolves_the_id_from_the_quoted_alert(): void
    {
        $this->adminAccount();
        $tx = $this->pendingDeposit();

        // The original alert, as it would have been recorded when sent.
        $alert = WhatsAppMessage::create([
            'wa_phone' => self::ADMIN_PHONE, 'direction' => 'out',
            'wa_message_id' => 'wamid.alert-1',
            'body' => "Jane submitted proof for a 10.00 USD manual_ecocash deposit (#{$tx->id}) via WhatsApp — verify and credit it in Transactions.",
        ]);

        app(MessageRouter::class)->handle($this->message('approve', self::ADMIN_PHONE, 'wamid.alert-1'));

        $this->assertSame('completed', $tx->fresh()->status);
    }

    public function test_a_customer_saying_received_is_never_treated_as_approval(): void
    {
        $customer = User::factory()->create(['balance' => 0]);
        WhatsAppAccount::create([
            'wa_phone' => self::CUSTOMER_PHONE, 'user_id' => $customer->id,
            'link_status' => 'linked', 'opted_in' => true,
        ]);
        $tx = Transaction::create([
            'user_id' => $customer->id, 'type' => 'deposit', 'amount' => 10.0,
            'balance_before' => 0, 'balance_after' => 0,
            'method' => 'manual_ecocash', 'status' => 'pending', 'notes' => 'awaiting proof',
        ]);

        app(MessageRouter::class)->handle($this->message("received {$tx->id}", self::CUSTOMER_PHONE));

        // Still pending — a customer can never self-approve their own deposit.
        $this->assertSame('pending', $tx->fresh()->status);
    }

    public function test_an_unknown_id_is_reported_not_silently_ignored(): void
    {
        $this->adminAccount();

        app(MessageRouter::class)->handle($this->message('APPROVE 999999', self::ADMIN_PHONE));

        $out = WhatsAppMessage::where('direction', 'out')->latest('id')->first();
        $this->assertStringContainsString('No pending deposit found', (string) $out->body);
    }

    public function test_an_already_completed_deposit_is_not_double_credited(): void
    {
        $this->adminAccount();
        $tx = $this->pendingDeposit();
        $tx->update(['status' => 'completed']);

        app(MessageRouter::class)->handle($this->message("APPROVE {$tx->id}", self::ADMIN_PHONE));

        $out = WhatsAppMessage::where('direction', 'out')->latest('id')->first();
        $this->assertStringContainsString('already', (string) $out->body);
    }

    public function test_a_bare_approve_with_nothing_quoted_is_left_alone(): void
    {
        // No id, no quoted alert — nothing to resolve, so this must fall
        // through untouched rather than erroring or guessing.
        $this->adminAccount();

        app(MessageRouter::class)->handle($this->message('approve', self::ADMIN_PHONE));

        $out = WhatsAppMessage::where('direction', 'out')->latest('id')->first();
        $this->assertStringNotContainsString('Approved', (string) ($out->body ?? ''));
    }
}
