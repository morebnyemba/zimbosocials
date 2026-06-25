<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'subject' => $this->faker->sentence(6),
            'message' => $this->faker->paragraph(),
            'status' => 'open',
            'priority' => $this->faker->randomElement(['low', 'normal', 'high']),
            'last_reply_at' => now(),
        ];
    }

    public function closed(): static
    {
        return $this->state(['status' => 'closed']);
    }

    public function resolved(): static
    {
        return $this->state(['status' => 'resolved']);
    }
}
