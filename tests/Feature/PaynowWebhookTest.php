<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Paynow\Payments\Paynow;
use Tests\TestCase;

class PaynowWebhookTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build the POST body that Paynow sends on a status update.
     * The hash is computed the same way the Paynow SDK does internally.
     */
    private function buildPaynowPayload(string $reference, string $status, string $integrationKey): array
    {
        $amount   = '10.00';
        $paynowRef = 'PNW-TEST-123';
        $currency  = 'USD';

        $hashString = mb_strtolower(
            $amount . $currency . $paynowRef . $reference . $status . $integrationKey
        );
        $hash = hash('sha512', $hashString);

        return [
            'reference'   => $reference,
            'paynowreference' => $paynowRef,
            'amount'      => $amount,
            'status'      => $status,
            'currencycode'=> $currency,
            'hash'        => $hash,
        ];
    }

    public function test_webhook_with_paid_status_credits_user_balance(): void
    {
        $user        = User::factory()->create(['balance' => 0]);
        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'type'    => 'deposit',
            'amount'  => 10.0,
            'status'  => 'pending',
        ]);

        // Mock the Paynow SDK to return a paid status
        $mockStatus = $this->getMockBuilder(\Paynow\Core\StatusResponse::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockStatus->method('paid')->willReturn(true);
        $mockStatus->method('status')->willReturn('paid');
        $mockStatus->method('reference')->willReturn((string) $transaction->id);

        $mockPaynow = $this->getMockBuilder(Paynow::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockPaynow->method('processStatusUpdate')->willReturn($mockStatus);

        $this->app->bind(Paynow::class, fn () => $mockPaynow);

        // Hit the webhook
        $this->postJson(route('paynow.update'), [])
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        $this->assertDatabaseHas('transactions', [
            'id'     => $transaction->id,
            'status' => 'completed',
        ]);

        $this->assertEquals(10.0, (float) $user->fresh()->balance);
    }

    public function test_webhook_ignores_already_completed_transaction(): void
    {
        $user        = User::factory()->create(['balance' => 10]);
        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'type'    => 'deposit',
            'amount'  => 10.0,
            'status'  => 'completed', // Already processed
        ]);

        $mockStatus = $this->getMockBuilder(\Paynow\Core\StatusResponse::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockStatus->method('paid')->willReturn(true);
        $mockStatus->method('status')->willReturn('paid');
        $mockStatus->method('reference')->willReturn((string) $transaction->id);

        $mockPaynow = $this->getMockBuilder(Paynow::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockPaynow->method('processStatusUpdate')->willReturn($mockStatus);

        $this->app->bind(Paynow::class, fn () => $mockPaynow);

        $this->postJson(route('paynow.update'), [])
            ->assertOk()
            ->assertJson(['status' => 'ignored']);

        // Balance should NOT change (no double credit)
        $this->assertEquals(10.0, (float) $user->fresh()->balance);
    }

    public function test_webhook_with_failed_status_marks_transaction_rejected(): void
    {
        $user        = User::factory()->create(['balance' => 0]);
        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'type'    => 'deposit',
            'amount'  => 10.0,
            'status'  => 'pending',
        ]);

        $mockStatus = $this->getMockBuilder(\Paynow\Core\StatusResponse::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockStatus->method('paid')->willReturn(false);
        $mockStatus->method('status')->willReturn('failed');
        $mockStatus->method('reference')->willReturn((string) $transaction->id);

        $mockPaynow = $this->getMockBuilder(Paynow::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockPaynow->method('processStatusUpdate')->willReturn($mockStatus);

        $this->app->bind(Paynow::class, fn () => $mockPaynow);

        $this->postJson(route('paynow.update'), [])
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        $this->assertDatabaseHas('transactions', [
            'id'     => $transaction->id,
            'status' => 'rejected',
        ]);

        // Balance unchanged
        $this->assertEquals(0, (float) $user->fresh()->balance);
    }

    public function test_webhook_returns_400_when_paynow_returns_null(): void
    {
        $mockPaynow = $this->getMockBuilder(Paynow::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockPaynow->method('processStatusUpdate')->willReturn(null);

        $this->app->bind(Paynow::class, fn () => $mockPaynow);

        $this->postJson(route('paynow.update'), [])
            ->assertStatus(400)
            ->assertJson(['status' => 'error']);
    }
}
