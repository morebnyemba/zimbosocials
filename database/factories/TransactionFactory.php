<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        $balanceBefore = $this->faker->randomFloat(2, 0, 500);
        $amount        = $this->faker->randomFloat(2, 1, 100);

        return [
            'user_id'        => User::factory(),
            'order_id'       => null,
            'type'           => 'deposit',
            'amount'         => $amount,
            'balance_before' => $balanceBefore,
            'balance_after'  => $balanceBefore + $amount,
            'method'         => 'paynow',
            'reference'      => 'TXN-' . strtoupper($this->faker->unique()->bothify('##??##??')),
            'status'         => 'completed',
            'notes'          => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => 'pending']);
    }

    public function orderCharge(): static
    {
        $amount = $this->faker->randomFloat(2, 0.01, 50);
        return $this->state(fn (array $attrs) => [
            'type'           => 'order_charge',
            'amount'         => -$amount,
            'balance_after'  => $attrs['balance_before'] - $amount,
        ]);
    }
}
