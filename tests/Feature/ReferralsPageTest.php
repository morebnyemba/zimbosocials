<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_referrals_page_and_get_referral_code(): void
    {
        $user = User::factory()->create([
            'referral_code' => null,
        ]);

        $response = $this->actingAs($user)->get(route('referrals.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Referrals/Index')
            ->where('summary.total_referrals', 0)
            ->where('rewardHistory', [])
        );

        $this->assertNotNull($user->fresh()->referral_code);
    }
}
