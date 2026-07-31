<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * A refunded or cancelled order is money that went back to the customer.
 * Counting its charge as revenue overstates the business — and every headline
 * figure was doing exactly that, so the dashboard reported takings that had
 * already been paid back.
 */
class RevenueExcludesRefundsTest extends TestCase
{
    use RefreshDatabase;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->service = Service::create([
            'name' => 'IG Followers', 'name_sn' => 'x', 'description' => '', 'description_sn' => '',
            'category' => 'Instagram', 'type' => 'followers', 'rate' => 1.0,
            'min_qty' => 100, 'max_qty' => 100000, 'is_active' => true,
        ]);
    }

    private function order(User $user, string $status, float $charge): Order
    {
        return Order::create([
            'user_id' => $user->id, 'service_id' => $this->service->id,
            'link' => 'https://instagram.com/jane', 'quantity' => 100,
            'charge' => $charge, 'rate_at_order' => 1.0, 'status' => $status,
        ]);
    }

    public function test_the_dashboard_counts_only_money_we_kept(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $customer = User::factory()->create();

        $this->order($customer, 'completed', 100);
        $this->order($customer, 'processing', 50);
        $this->order($customer, 'refunded', 500);   // paid back
        $this->order($customer, 'cancelled', 300);  // never earned

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                // 150, not 950.
                ->where('stats.revenue', 150)
                ->where('stats.today_revenue', 150)
                ->where('stats.month_revenue', 150)
                // The order COUNT still includes them — they did happen.
                ->where('stats.orders', 4)
            );
    }

    public function test_the_revenue_page_agrees_with_the_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $customer = User::factory()->create();

        $this->order($customer, 'completed', 100);
        $this->order($customer, 'refunded', 500);

        // Two screens disagreeing about revenue is worse than either being wrong.
        $this->actingAs($admin)
            ->get(route('admin.revenue'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('summary.total_revenue', 100));
    }

    public function test_a_customer_is_not_shown_as_having_spent_a_refund(): void
    {
        $customer = User::factory()->create();
        $this->order($customer, 'completed', 40);
        $this->order($customer, 'refunded', 60);

        $this->actingAs($customer)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('stats.total_spent', 40));
    }

    public function test_partial_orders_still_count(): void
    {
        // A partial order delivered something and kept some of the money, so it
        // is not in the same bucket as a full refund.
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $customer = User::factory()->create();
        $this->order($customer, 'partial', 80);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertInertia(fn ($page) => $page->where('stats.revenue', 80));
    }

    public function test_the_earning_scope_is_the_single_definition(): void
    {
        $customer = User::factory()->create();
        $this->order($customer, 'completed', 10);
        $this->order($customer, 'refunded', 10);
        $this->order($customer, 'cancelled', 10);

        $this->assertSame(10.0, (float) Order::earning()->sum('charge'));
        $this->assertSame(3, Order::count());
    }
}
