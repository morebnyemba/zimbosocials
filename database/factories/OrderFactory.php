<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(100, 5000);
        $rate     = $this->faker->randomFloat(4, 0.1, 5.0);
        $charge   = round(($quantity / 1000) * $rate, 4);

        return [
            'user_id'        => User::factory(),
            'service_id'     => Service::factory(),
            'link'           => $this->faker->url(),
            'quantity'       => $quantity,
            'charge'         => $charge,
            'rate_at_order'  => $rate,
            'status'         => 'pending',
            'start_count'    => null,
            'remains'        => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(['status' => 'completed']);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => 'cancelled']);
    }

    public function processing(): static
    {
        return $this->state(['status' => 'processing']);
    }
}
