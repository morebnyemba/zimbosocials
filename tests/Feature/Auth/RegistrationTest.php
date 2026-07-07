<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'username' => 'test_user',
            'email' => 'test@example.com',
            'whatsapp_number' => '263771234567',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_new_user_can_register_with_referral_code(): void
    {
        $referrer = User::factory()->create([
            'referral_code' => 'ZIMREFTEST1',
        ]);

        $response = $this->post('/register', [
            'name' => 'Referred User',
            'username' => 'referred_user',
            'email' => 'referred@example.com',
            'whatsapp_number' => '263771234568',
            'password' => 'password',
            'password_confirmation' => 'password',
            'referral_code' => 'ZIMREFTEST1',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));

        $this->assertDatabaseHas('users', [
            'email' => 'referred@example.com',
            'referred_by' => $referrer->getKey(),
        ]);
    }
}
