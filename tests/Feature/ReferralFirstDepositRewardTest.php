<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralFirstDepositRewardTest extends TestCase
{
    use RefreshDatabase;

    public function test_referrer_is_rewarded_when_referred_user_first_deposit_is_approved(): void
    {
        config(['services.referral.first_deposit_reward' => 2.50]);

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $referrer = User::factory()->create([
            'balance' => 0,
            'referral_code' => 'ZIMREFA001',
        ]);

        $referred = User::factory()->create([
            'referred_by' => $referrer->getKey(),
            'balance' => 0,
            'referred_bonus_awarded_at' => null,
        ]);

        $deposit = Transaction::create([
            'user_id' => $referred->getKey(),
            'type' => 'deposit',
            'amount' => 10,
            'balance_before' => 0,
            'balance_after' => 0,
            'status' => 'pending',
            'method' => 'innbucks',
        ]);

        $response = $this->actingAs($admin)->post('/admin/transactions/'.$deposit->getKey().'/approve');

        $response->assertRedirect();

        $referrer->refresh();
        $referred->refresh();

        $this->assertEquals(2.5, (float) $referrer->balance);
        $this->assertNotNull($referred->referred_bonus_awarded_at);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $referrer->getKey(),
            'type' => 'bonus',
            'method' => 'referral',
            'status' => 'completed',
            'amount' => 2.5,
        ]);
    }

    public function test_referrer_is_not_rewarded_again_on_second_deposit(): void
    {
        config(['services.referral.first_deposit_reward' => 1.00]);

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $referrer = User::factory()->create([
            'balance' => 0,
            'referral_code' => 'ZIMREFA002',
        ]);

        $referred = User::factory()->create([
            'referred_by' => $referrer->getKey(),
            'balance' => 0,
            'referred_bonus_awarded_at' => null,
        ]);

        $firstDeposit = Transaction::create([
            'user_id' => $referred->getKey(),
            'type' => 'deposit',
            'amount' => 10,
            'balance_before' => 0,
            'balance_after' => 0,
            'status' => 'pending',
            'method' => 'innbucks',
        ]);

        $secondDeposit = Transaction::create([
            'user_id' => $referred->getKey(),
            'type' => 'deposit',
            'amount' => 15,
            'balance_before' => 10,
            'balance_after' => 10,
            'status' => 'pending',
            'method' => 'innbucks',
        ]);

        $this->actingAs($admin)->post('/admin/transactions/'.$firstDeposit->getKey().'/approve');
        $this->actingAs($admin)->post('/admin/transactions/'.$secondDeposit->getKey().'/approve');

        $referrer->refresh();

        $this->assertEquals(1.0, (float) $referrer->balance);

        $bonusCount = Transaction::query()
            ->where('user_id', $referrer->getKey())
            ->where('type', 'bonus')
            ->where('method', 'referral')
            ->count();

        $this->assertSame(1, $bonusCount);
    }
}
