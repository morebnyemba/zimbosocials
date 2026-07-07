<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApiControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function apiUser(array $attrs = []): User
    {
        // Keys are stored hashed; keep the plaintext on the in-memory model
        // only so the tests' `$user->api_key` reads keep working.
        $key = $attrs['api_key'] ?? 'test-api-key-'.Str::random(8);
        unset($attrs['api_key']);

        $user = User::factory()->create(array_merge([
            'role' => 'reseller',
            'is_active' => true,
            'balance' => 50,
            'api_key_hash' => hash('sha256', $key),
            'api_key_last4' => substr($key, -4),
        ], $attrs));

        $user->api_key = $key;

        return $user;
    }

    private function activeService(array $attrs = []): Service
    {
        return Service::factory()->create(array_merge([
            'is_active' => true,
            'category' => 'Instagram',
            'rate' => 1.0,   // $1 per 1000 → 100 qty = $0.10
            'min_qty' => 100,
            'max_qty' => 100000,
        ], $attrs));
    }

    // ─── GET /api/v1/services ─────────────────────────────────────────────────

    public function test_services_list_returns_active_services(): void
    {
        $user = $this->apiUser();
        $service = $this->activeService();

        $response = $this->withToken($user->api_key)->getJson('/api/v1/services');

        $response->assertOk()
            ->assertJsonFragment(['service' => $service->id]);
    }

    public function test_services_list_requires_bearer_token(): void
    {
        $this->getJson('/api/v1/services')
            ->assertUnauthorized();
    }

    public function test_services_list_rejects_query_string_key(): void
    {
        $user = $this->apiUser();

        $this->getJson('/api/v1/services?key='.$user->api_key)
            ->assertUnauthorized();
    }

    // ─── GET /api/v1/balance ─────────────────────────────────────────────────

    public function test_balance_endpoint_returns_user_balance(): void
    {
        $user = $this->apiUser(['balance' => 42.50]);

        $this->withToken($user->api_key)
            ->getJson('/api/v1/balance')
            ->assertOk()
            ->assertJson(['balance' => 42.50, 'currency' => 'USD']);
    }

    // ─── POST /api/v1/order ──────────────────────────────────────────────────

    public function test_place_order_succeeds_and_deducts_balance(): void
    {
        $user = $this->apiUser(['balance' => 10.0]);
        $service = $this->activeService(['rate' => 1.0]);

        $response = $this->withToken($user->api_key)->postJson('/api/v1/order', [
            'service' => $service->id,
            'link' => 'https://instagram.com/zimbo.profile',
            'quantity' => 1000,
        ]);

        $response->assertOk()
            ->assertJsonStructure(['order', 'upstream_pushed']);

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'service_id' => $service->id,
            'status' => 'pending',
        ]);

        // Balance should be deducted
        $this->assertLessThan(10.0, (float) $user->fresh()->balance);
    }

    public function test_place_order_returns_402_when_insufficient_balance(): void
    {
        $user = $this->apiUser(['balance' => 0.001]);
        $service = $this->activeService(['rate' => 5.0]);

        $this->withToken($user->api_key)->postJson('/api/v1/order', [
            'service' => $service->id,
            'link' => 'https://instagram.com/zimbo.profile',
            'quantity' => 1000,
        ])->assertStatus(402)
            ->assertJsonFragment(['error' => 'Insufficient balance.']);
    }

    public function test_place_order_returns_422_for_invalid_quantity(): void
    {
        $user = $this->apiUser(['balance' => 100]);
        $service = $this->activeService(['min_qty' => 100, 'max_qty' => 5000]);

        $this->withToken($user->api_key)->postJson('/api/v1/order', [
            'service' => $service->id,
            'link' => 'https://instagram.com/zimbo.profile',
            'quantity' => 9999999,
        ])->assertStatus(422);
    }

    public function test_place_order_requires_authentication(): void
    {
        $service = $this->activeService();

        $this->postJson('/api/v1/order', [
            'service' => $service->id,
            'link' => 'https://instagram.com/zimbo.profile',
            'quantity' => 500,
        ])->assertUnauthorized();
    }

    // ─── GET /api/v1/status ──────────────────────────────────────────────────

    public function test_order_status_returns_order_details(): void
    {
        $user = $this->apiUser();
        $order = Order::factory()->create(['user_id' => $user->id]);

        $this->withToken($user->api_key)
            ->getJson('/api/v1/status?order='.$order->id)
            ->assertOk()
            ->assertJsonFragment(['order' => $order->id]);
    }

    public function test_order_status_returns_404_for_other_users_order(): void
    {
        $user1 = $this->apiUser();
        $user2 = $this->apiUser(['api_key' => 'other-key-'.Str::random(8)]);
        $order = Order::factory()->create(['user_id' => $user2->id]);

        $this->withToken($user1->api_key)
            ->getJson('/api/v1/status?order='.$order->id)
            ->assertNotFound();
    }

    // ─── POST /api/v1/cancel ─────────────────────────────────────────────────

    public function test_cancel_order_refunds_balance(): void
    {
        $user = $this->apiUser(['balance' => 6.0]);
        $service = $this->activeService(['rate' => 1.0]);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'service_id' => $service->id,
            'charge' => 1.0,
            'status' => 'pending',
        ]);
        // Refunds pay back only what was actually charged — record the charge
        // the same way production order placement does.
        $user->deductBalance(1.0, $order, 'Order #'.$order->id);

        $this->withToken($user->api_key)
            ->postJson('/api/v1/cancel', ['order' => $order->id])
            ->assertOk()
            ->assertJson(['cancel' => $order->id]);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled']);
        $this->assertGreaterThan(5.0, (float) $user->fresh()->balance);
    }
}
