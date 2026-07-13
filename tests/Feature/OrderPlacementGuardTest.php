<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderPlacementGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_empty_balance_cannot_place_order(): void
    {
        $user = User::factory()->create([
            'balance' => 0,
            'role' => 'user',
        ]);

        $service = Service::create([
            'name' => 'Instagram Followers',
            'name_sn' => 'Instagram Vateveri',
            'description' => 'Test service',
            'description_sn' => 'Test service',
            'category' => 'instagram',
            'type' => 'followers',
            'rate' => 0,
            'min_qty' => 10,
            'max_qty' => 10000,
            'is_active' => true,
            'display_order' => 1,
        ]);

        $response = $this->actingAs($user)->from('/orders/new')->post('/orders', [
            'service_id' => $service->id,
            'link' => 'https://instagram.com/testuser',
            'quantity' => 100,
        ]);

        $response->assertRedirect('/orders/new');
        $response->assertSessionHasErrors(['balance']);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_user_with_insufficient_balance_cannot_place_order(): void
    {
        $user = User::factory()->create([
            'balance' => 0.05,
            'role' => 'user',
        ]);

        $service = Service::create([
            'name' => 'Instagram Followers',
            'name_sn' => 'Instagram Vateveri',
            'description' => 'Test service',
            'description_sn' => 'Test service',
            'category' => 'instagram',
            'type' => 'followers',
            'rate' => 1.00,
            'min_qty' => 10,
            'max_qty' => 10000,
            'is_active' => true,
            'display_order' => 1,
        ]);

        // charge = (100 / 1000) * 1.00 = 0.10, balance = 0.05
        $response = $this->actingAs($user)->from('/orders/new')->post('/orders', [
            'service_id' => $service->id,
            'link' => 'https://instagram.com/testuser',
            'quantity' => 100,
        ]);

        $response->assertRedirect('/orders/new');
        $response->assertSessionHasErrors(['balance']);
        $this->assertDatabaseCount('orders', 0);
    }
}
