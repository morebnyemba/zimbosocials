<?php

namespace Tests\Feature\Auth;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    public function test_admin_login_requires_email_code(): void
    {
        Mail::fake();
        $admin = $this->admin();

        $response = $this->post('/login', [
            'login' => $admin->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('2fa.show'));
        $this->assertGuest();
        $this->assertNotNull(Cache::get("admin2fa:code:{$admin->id}"));
    }

    public function test_correct_code_completes_admin_login(): void
    {
        Mail::fake();
        $admin = $this->admin();

        $this->post('/login', ['login' => $admin->email, 'password' => 'password']);

        // Overwrite with a known code — only the hash is ever stored.
        Cache::put("admin2fa:code:{$admin->id}", hash('sha256', '123456'), now()->addMinutes(10));

        $response = $this->post(route('2fa.verify'), ['code' => '123456']);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_wrong_code_is_rejected_and_attempts_limited(): void
    {
        Mail::fake();
        $admin = $this->admin();

        $this->post('/login', ['login' => $admin->email, 'password' => 'password']);
        Cache::put("admin2fa:code:{$admin->id}", hash('sha256', '123456'), now()->addMinutes(10));

        $this->post(route('2fa.verify'), ['code' => '000000'])->assertSessionHasErrors(['code']);
        $this->assertGuest();

        // Burn through the attempt budget — the 6th try kicks back to login.
        foreach (range(1, 4) as $i) {
            $this->post(route('2fa.verify'), ['code' => '000000']);
        }
        $this->post(route('2fa.verify'), ['code' => '123456'])
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_regular_users_log_in_without_a_code(): void
    {
        Mail::fake();
        $user = User::factory()->create(['role' => 'user']);

        $this->post('/login', ['login' => $user->email, 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_2fa_can_be_disabled_via_setting(): void
    {
        Mail::fake();
        Setting::set('admin_2fa_enabled', '0', 'security');
        $admin = $this->admin();

        $this->post('/login', ['login' => $admin->email, 'password' => 'password']);

        $this->assertAuthenticatedAs($admin);
    }
}
