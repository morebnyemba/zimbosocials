<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use App\Services\OrderService;
use App\Services\Upstream\OrderDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The order + charge commit in a transaction; everything after (the upstream
 * push, admin notify) is best-effort. A failure there must never bubble into a
 * 500 for a customer whose order actually went through — the order simply stays
 * pending for the recover-pending command to finish.
 */
class OrderPlacementResilienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_throwing_dispatch_does_not_fail_the_placed_order(): void
    {
        $service = Service::create([
            'name' => 'IG Followers', 'name_sn' => 'x', 'description' => '', 'description_sn' => '',
            'category' => 'Instagram', 'type' => 'followers', 'rate' => 1.0,
            'min_qty' => 100, 'max_qty' => 100000, 'is_active' => true,
        ]);
        $user = User::factory()->create(['balance' => 500]);

        // A dispatch service that blows up mid-push (e.g. provider client error).
        $exploding = new class extends OrderDispatchService
        {
            public function __construct() {}

            public function dispatch(\App\Models\Order $order): array
            {
                throw new \RuntimeException('provider exploded');
            }
        };

        $result = app(OrderService::class)->placeOrder(
            $user, $service, 'https://instagram.com/jane', 1000, $exploding, 'test'
        );

        // The order succeeded and was charged; dispatch is reported as failed.
        $this->assertTrue($result['ok']);
        $this->assertFalse($result['dispatch']['ok']);
        $this->assertDatabaseHas('orders', [
            'id' => $result['order']->id, 'status' => 'pending', 'pushed_to_upstream' => false,
        ]);
        $this->assertLessThan(500.0, (float) $user->fresh()->balance); // charge debited
    }
}
