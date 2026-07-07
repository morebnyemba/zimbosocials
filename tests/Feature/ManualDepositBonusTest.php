<?php

namespace Tests\Feature;

use App\Models\ManualPaymentDetail;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DepositService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The manual-deposit bonus: every MANUAL (non-gateway) deposit earns a
 * configurable instant bonus (default 5%). Gateway deposits never do. On a
 * referred user's first qualifying deposit it stacks with the 10% welcome
 * bonus, so their first manual deposit effectively earns 15%.
 */
class ManualDepositBonusTest extends TestCase
{
    use RefreshDatabase;

    private function makeManualMethod(string $key = 'bank'): void
    {
        ManualPaymentDetail::create([
            'method_key' => $key,
            'label' => strtoupper($key),
            'is_active' => true,
            'sort_order' => 1,
            'gateway_type' => null,
        ]);
    }

    private function makePendingDeposit(User $user, float $amount, string $method): Transaction
    {
        return Transaction::create([
            'user_id' => $user->id,
            'type' => 'deposit',
            'amount' => $amount,
            'balance_before' => (float) $user->balance,
            'balance_after' => (float) $user->balance,
            'method' => $method,
            'status' => 'pending',
        ]);
    }

    public function test_manual_deposit_credits_five_percent_bonus(): void
    {
        $this->makeManualMethod('bank');
        $user = User::factory()->create(['balance' => 0]);
        $deposit = $this->makePendingDeposit($user, 100, 'bank');

        $credited = app(DepositService::class)->credit($deposit, 'admin_approval');

        $this->assertTrue($credited);

        $user->refresh();
        $this->assertEquals(105.0, (float) $user->balance); // 100 deposit + 5% bonus

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type' => 'bonus',
            'method' => 'deposit_bonus',
            'reference' => 'DEPOSIT-BONUS-'.$deposit->getKey(),
        ]);
    }

    public function test_bonus_is_not_double_paid_when_credit_races(): void
    {
        $this->makeManualMethod('bank');
        $user = User::factory()->create(['balance' => 0]);
        $deposit = $this->makePendingDeposit($user, 100, 'bank');

        $service = app(DepositService::class);
        $service->credit($deposit, 'admin_approval');
        $service->credit($deposit->fresh(), 'poll'); // second resolution attempt

        $user->refresh();
        $this->assertEquals(105.0, (float) $user->balance);

        $this->assertSame(1, Transaction::where('user_id', $user->id)
            ->where('type', 'bonus')
            ->where('method', 'deposit_bonus')
            ->count());
    }

    public function test_gateway_deposit_gets_no_manual_bonus(): void
    {
        ManualPaymentDetail::create([
            'method_key' => 'ecocash',
            'label' => 'EcoCash',
            'is_active' => true,
            'sort_order' => 1,
            'gateway_type' => 'paynow',
        ]);

        $user = User::factory()->create(['balance' => 0]);
        $deposit = $this->makePendingDeposit($user, 100, 'ecocash');

        app(DepositService::class)->credit($deposit, 'paynow_webhook');

        $user->refresh();
        $this->assertEquals(100.0, (float) $user->balance); // no bonus

        $this->assertSame(0, Transaction::where('user_id', $user->id)
            ->where('method', 'deposit_bonus')
            ->count());
    }

    public function test_referred_user_first_manual_deposit_earns_fifteen_percent_total(): void
    {
        config([
            'services.referral.referred_first_deposit_bonus_percent' => 10.0,
            'services.referral.min_qualifying_deposit' => 5.0,
            'services.referral.first_deposit_reward' => 1.0,
        ]);

        $this->makeManualMethod('bank');

        $referrer = User::factory()->create(['balance' => 0]);
        $referred = User::factory()->create(['balance' => 0, 'referred_by' => $referrer->id]);

        $deposit = $this->makePendingDeposit($referred, 100, 'bank');
        app(DepositService::class)->credit($deposit, 'admin_approval');

        $referred->refresh();
        // 100 deposit + 5 manual bonus + 10 welcome bonus = 115 (15% total bonus)
        $this->assertEquals(115.0, (float) $referred->balance);

        $referrer->refresh();
        $this->assertEquals(1.0, (float) $referrer->balance); // flat first-deposit reward
    }
}
