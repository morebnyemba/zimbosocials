<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Paynow\Core\StatusResponse;
use Paynow\Payments\Paynow;
use Tests\TestCase;

/**
 * The active gateway poller: a WhatsApp/abandoned Paynow deposit auto-resolves
 * within a minute even though nothing else is watching it.
 */
class PollPendingGatewayDepositsTest extends TestCase
{
    use RefreshDatabase;

    private function mockPaynow(string $paynowStatus): void
    {
        // Real StatusResponse (status() lowercases its input) so the test
        // exercises the exact case-handling the reject path depends on.
        $status = new StatusResponse(['status' => $paynowStatus]);

        // Anonymous Paynow subclass — a PHPUnit mock of pollTransaction here
        // fails to intercept and makes a real network call that hangs.
        $paynow = new class($status) extends Paynow {
            public function __construct(private StatusResponse $canned) {}

            public function pollTransaction($url)
            {
                return $this->canned;
            }
        };

        $this->app->bind(Paynow::class, fn () => $paynow);
    }

    private function pendingGatewayDeposit(User $user): Transaction
    {
        return Transaction::factory()->create([
            'user_id' => $user->id, 'type' => 'deposit', 'amount' => 10.0,
            'method' => 'ecocash', 'status' => 'pending',
            'reference' => 'https://paynow.co.zw/interface/poll/?guid=abc',
        ]);
    }

    public function test_failed_ecocash_deposit_is_auto_rejected(): void
    {
        $user = User::factory()->create(['balance' => 0]);
        $tx = $this->pendingGatewayDeposit($user);

        // Paynow reports the express push failed (e.g. insufficient funds).
        $this->mockPaynow('Failed');

        $this->artisan('transactions:poll-gateway')->assertSuccessful();

        $this->assertSame('rejected', $tx->fresh()->status);
        $this->assertEquals(0.0, (float) $user->fresh()->balance); // no credit
    }

    public function test_paid_deposit_is_auto_credited(): void
    {
        $user = User::factory()->create(['balance' => 0]);
        $tx = $this->pendingGatewayDeposit($user);

        $this->mockPaynow('Paid');

        $this->artisan('transactions:poll-gateway')->assertSuccessful();

        $this->assertSame('completed', $tx->fresh()->status);
        $this->assertEquals(10.0, (float) $user->fresh()->balance);
    }

    public function test_manual_and_stale_deposits_are_left_alone(): void
    {
        $user = User::factory()->create(['balance' => 0]);

        // Manual deposit: no poll-URL reference.
        $manual = Transaction::factory()->create([
            'user_id' => $user->id, 'type' => 'deposit', 'amount' => 5.0,
            'method' => 'bank', 'status' => 'pending', 'reference' => null,
        ]);
        // Gateway deposit older than the active window.
        $old = $this->pendingGatewayDeposit($user);
        $old->forceFill(['created_at' => now()->subHours(2)])->save();

        $this->mockPaynow('Paid'); // would credit if polled

        $this->artisan('transactions:poll-gateway')->assertSuccessful();

        $this->assertSame('pending', $manual->fresh()->status);
        $this->assertSame('pending', $old->fresh()->status);
    }
}
