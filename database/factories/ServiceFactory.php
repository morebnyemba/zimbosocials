<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        $rate = $this->faker->randomFloat(4, 0.1, 5.0);
        return [
            'name'          => $this->faker->words(3, true),
            'category'      => $this->faker->randomElement(['Instagram', 'TikTok', 'YouTube', 'Facebook', 'Twitter']),
            'description'   => $this->faker->sentence(),
            'rate'          => $rate,
            'min_qty'       => 100,
            'max_qty'       => 100000,
            'display_order' => $this->faker->numberBetween(1, 100),
            'is_active'     => true,
            'is_dripfeed'   => false,
            'is_refill'     => false,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
