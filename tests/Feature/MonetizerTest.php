<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonetizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketer_can_view_monetizer_page(): void
    {
        $marketer = User::factory()->create([
            'role' => 'marketer',
            'is_active' => true,
        ]);

        $response = $this->actingAs($marketer)
            ->get(route('monetizer.index'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Monetizer/Index')
                ->where('isUnlocked', false)
                ->where('canUnlock', false)
            );
    }

    public function test_non_marketer_cannot_access_monetizer(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('monetizer.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('monetizer.unlock'))
            ->assertForbidden();
    }

    public function test_monetizer_unlocks_when_threshold_met(): void
    {
        Service::factory()->create(['is_active' => true]);

        $marketer = User::factory()->create([
            'role' => 'marketer',
            'is_active' => true,
        ]);

        Transaction::create([
            'user_id' => $marketer->id,
            'type' => 'deposit',
            'amount' => 100.00,
            'balance_before' => 0,
            'balance_after' => 100.00,
            'status' => 'completed',
            'method' => 'test',
        ]);

        $this->actingAs($marketer)
            ->get(route('monetizer.index'))
            ->assertInertia(fn ($page) => $page
                ->where('canUnlock', true)
                ->where('progress', 100)
            );

        $response = $this->actingAs($marketer)
            ->post(route('monetizer.unlock'));

        $response->assertRedirect(route('monetizer.index'))
            ->assertSessionHas('success');

        $this->assertNotNull($marketer->fresh()->monetizer_unlocked_at);
    }

    public function test_monetizer_considers_order_spend(): void
    {
        $service = Service::factory()->create(['is_active' => true]);

        $marketer = User::factory()->create([
            'role' => 'marketer',
            'is_active' => true,
        ]);

        Order::create([
            'user_id' => $marketer->id,
            'service_id' => $service->id,
            'link' => 'https://example.com',
            'quantity' => 100,
            'charge' => 75.00,
            'rate_at_order' => 0.75,
            'status' => 'completed',
        ]);

        Transaction::create([
            'user_id' => $marketer->id,
            'type' => 'deposit',
            'amount' => 25.00,
            'balance_before' => 0,
            'balance_after' => 25.00,
            'status' => 'completed',
            'method' => 'test',
        ]);

        $this->actingAs($marketer)
            ->get(route('monetizer.index'))
            ->assertInertia(fn ($page) => $page
                ->where('activityTotal', 100)
                ->where('canUnlock', true)
            );
    }

    public function test_unlock_fails_when_threshold_not_met(): void
    {
        $marketer = User::factory()->create([
            'role' => 'marketer',
            'is_active' => true,
        ]);

        $this->actingAs($marketer)
            ->post(route('monetizer.unlock'))
            ->assertForbidden();

        $this->assertNull($marketer->fresh()->monetizer_unlocked_at);
    }

    public function test_admin_settings_expose_monetizer_defaults(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertInertia(fn ($page) => $page
                ->has('monetizerDefaults')
                ->where('monetizerDefaults.threshold_usd', '100.00')
                ->where('monetizerDefaults.lookback_days', '90')
            );
    }

    public function test_user_can_save_platform_profiles(): void
    {
        $marketer = User::factory()->create([
            'role' => 'marketer',
            'is_active' => true,
        ]);

        $this->actingAs($marketer)
            ->post(route('monetizer.profiles.update'), [
                'youtube_channel_id' => 'UCtest123',
                'facebook_page_id' => 'myPage',
                'tiktok_username' => '@creator',
                'instagram_username' => '@creator',
                'x_username' => '@creator',
            ])
            ->assertRedirect(route('monetizer.index'))
            ->assertSessionHas('success');

        $marketer->refresh();
        $this->assertEquals('UCtest123', $marketer->youtube_channel_id);
        $this->assertEquals('@creator', $marketer->tiktok_username);
    }

    public function test_user_can_save_manual_platform_stats(): void
    {
        $marketer = User::factory()->create([
            'role' => 'marketer',
            'is_active' => true,
        ]);

        $this->actingAs($marketer)
            ->post(route('monetizer.manual-stats.update'), [
                'platform' => 'youtube',
                'metric_key' => 'watch_hours',
                'value' => 2500,
            ])
            ->assertRedirect(route('monetizer.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('social_platform_stats', [
            'user_id' => $marketer->id,
            'platform' => 'youtube',
            'metric_key' => 'watch_hours',
            'value' => 2500,
            'source' => 'manual',
        ]);
    }

    public function test_managers_are_assigned_when_platform_threshold_is_met(): void
    {
        $accountManager = User::factory()->create([
            'role' => 'marketer',
            'manager_role' => 'account_manager',
            'is_active' => true,
        ]);
        $supportManager = User::factory()->create([
            'role' => 'marketer',
            'manager_role' => 'support_manager',
            'is_active' => true,
        ]);

        $service = Service::factory()->create([
            'category' => 'twitter',
            'name' => 'Twitter Followers',
            'is_active' => true,
        ]);

        $marketer = User::factory()->create([
            'role' => 'marketer',
            'is_active' => true,
        ]);

        Order::create([
            'user_id' => $marketer->id,
            'service_id' => $service->id,
            'link' => 'https://x.com/test',
            'quantity' => 500,
            'charge' => 25.00,
            'rate_at_order' => 25.00,
            'status' => 'completed',
        ]);

        $this->actingAs($marketer)
            ->get(route('monetizer.index'))
            ->assertInertia(fn ($page) => $page
                ->where('hasReachedPlatformThreshold', true)
                ->where('managers.account.name', $accountManager->name)
                ->where('managers.support.name', $supportManager->name)
            );

        $marketer->refresh();
        $this->assertEquals($accountManager->id, $marketer->account_manager_id);
        $this->assertEquals($supportManager->id, $marketer->support_manager_id);
    }
}
