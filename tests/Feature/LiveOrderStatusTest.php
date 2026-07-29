<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Services\Upstream\OrderStatusSyncService;
use App\WhatsApp\Order\LiveOrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

/**
 * When someone asks whether their order is done, the answer has to be true at
 * the moment it is sent. The scheduled sync runs every five minutes, so without
 * an on-demand check the assistant can confidently say "still processing" about
 * an order that finished four minutes ago — which makes people ask again, and
 * then doubt us.
 */
class LiveOrderStatusTest extends TestCase
{
    use RefreshDatabase;

    private function order(string $status = 'processing', bool $pushed = true): Order
    {
        $user = User::factory()->create();
        $service = Service::create([
            'name' => 'IG Followers', 'name_sn' => 'x', 'description' => '', 'description_sn' => '',
            'category' => 'Instagram', 'type' => 'followers', 'rate' => 1.0,
            'min_qty' => 100, 'max_qty' => 100000, 'is_active' => true,
        ]);

        return Order::create([
            'user_id' => $user->id, 'service_id' => $service->id, 'link' => 'https://instagram.com/jane',
            'quantity' => 2000, 'charge' => 2.0, 'rate_at_order' => 1.0, 'status' => $status,
            'external_order_id' => $pushed ? 'EXT-1' : null, 'pushed_to_upstream' => $pushed,
        ]);
    }

    public function test_it_recognises_the_ways_people_ask(): void
    {
        $live = app(LiveOrderStatus::class);

        foreach ([
            'is my order done?', 'any update?', 'zvaita here?', 'matii', 'has it started',
            'my followers have not come yet', 'how long will it take', 'still processing?',
        ] as $question) {
            $this->assertTrue($live->isStatusQuestion($question), "should recognise: {$question}");
        }

        // And doesn't fire on ordinary sales talk, which would mean an upstream
        // call on messages that have nothing to do with an order.
        foreach (['i want 1000 tiktok followers', 'how much for likes', 'hie'] as $other) {
            $this->assertFalse($live->isStatusQuestion($other), "should ignore: {$other}");
        }
    }

    public function test_an_in_flight_order_is_checked_with_the_provider(): void
    {
        $order = $this->order('processing');

        $sync = Mockery::mock(OrderStatusSyncService::class);
        $sync->shouldReceive('syncSingleOrder')->once()->andReturn(['changed' => true]);
        $this->app->instance(OrderStatusSyncService::class, $sync);

        $this->assertSame(1, app(LiveOrderStatus::class)->refresh($order->user));
    }

    public function test_finished_orders_are_left_alone(): void
    {
        $order = $this->order('completed');

        $sync = Mockery::mock(OrderStatusSyncService::class);
        $sync->shouldNotReceive('syncSingleOrder');
        $this->app->instance(OrderStatusSyncService::class, $sync);

        $this->assertSame(0, app(LiveOrderStatus::class)->refresh($order->user));
    }

    public function test_an_order_never_sent_upstream_is_not_polled(): void
    {
        $order = $this->order('pending', pushed: false);

        $sync = Mockery::mock(OrderStatusSyncService::class);
        $sync->shouldNotReceive('syncSingleOrder');
        $this->app->instance(OrderStatusSyncService::class, $sync);

        $this->assertSame(0, app(LiveOrderStatus::class)->refresh($order->user));
    }

    public function test_asking_repeatedly_does_not_hammer_the_provider(): void
    {
        $order = $this->order('processing');

        $sync = Mockery::mock(OrderStatusSyncService::class);
        // Once, despite three questions in a row.
        $sync->shouldReceive('syncSingleOrder')->once()->andReturn(['changed' => true]);
        $this->app->instance(OrderStatusSyncService::class, $sync);

        $live = app(LiveOrderStatus::class);
        $live->refresh($order->user);
        $live->refresh($order->user);
        $live->refresh($order->user);
    }

    public function test_a_provider_failure_never_costs_the_customer_a_reply(): void
    {
        $order = $this->order('processing');
        Cache::flush();

        $sync = Mockery::mock(OrderStatusSyncService::class);
        $sync->shouldReceive('syncSingleOrder')->andThrow(new \RuntimeException('provider down'));
        $this->app->instance(OrderStatusSyncService::class, $sync);

        // Swallowed: we answer from what we already know rather than erroring.
        $this->assertSame(0, app(LiveOrderStatus::class)->refresh($order->user));
    }
}
