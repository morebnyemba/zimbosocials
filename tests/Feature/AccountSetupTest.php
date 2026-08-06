<?php

namespace Tests\Feature;

use App\Models\User;
use App\WhatsApp\Auth\WhatsAppRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Lets a WhatsApp-registered user (still on the auto-generated username,
 * password, and possibly placeholder email — see WhatsAppRegistrar) replace
 * them with their own, without needing to know the generated password.
 */
class AccountSetupTest extends TestCase
{
    use RefreshDatabase;

    private function waUser(?string $email = null): User
    {
        return User::factory()->create([
            'username' => 'wa'.fake()->unique()->numberBetween(1000, 9999),
            'email' => $email ?? WhatsAppRegistrar::autoEmailFor('263771234567'),
            'credentials_set_at' => null,
        ]);
    }

    public function test_the_page_flags_a_placeholder_email(): void
    {
        $user = $this->waUser();

        $this->actingAs($user)
            ->get(route('account.setup.edit'))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Account/Setup')
                ->where('is_placeholder_email', true)
            );
    }

    public function test_a_real_email_is_not_flagged_as_a_placeholder(): void
    {
        $user = $this->waUser('real.person@example.com');

        $this->actingAs($user)
            ->get(route('account.setup.edit'))
            ->assertInertia(fn (AssertableInertia $p) => $p->where('is_placeholder_email', false));
    }

    public function test_setting_your_own_details_clears_the_flag(): void
    {
        $user = $this->waUser();

        $this->actingAs($user)
            ->post(route('account.setup.update'), [
                'username' => 'mynewusername',
                'email' => 'mynewusername@example.com',
                'password' => 'a-strong-password',
                'password_confirmation' => 'a-strong-password',
            ])
            ->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertSame('mynewusername', $user->username);
        $this->assertSame('mynewusername@example.com', $user->email);
        $this->assertTrue(Hash::check('a-strong-password', $user->password));
        $this->assertNotNull($user->credentials_set_at);
        $this->assertFalse($user->needsCredentialsSetup());
    }

    public function test_no_current_password_is_required(): void
    {
        // The whole point: the auto-generated password was never told to them.
        $user = $this->waUser();

        $response = $this->actingAs($user)->post(route('account.setup.update'), [
            'username' => 'anotherusername',
            'email' => 'another@example.com',
            'password' => 'a-strong-password',
            'password_confirmation' => 'a-strong-password',
        ]);

        $response->assertSessionDoesntHaveErrors();
    }

    public function test_a_taken_username_is_rejected(): void
    {
        User::factory()->create(['username' => 'taken']);
        $user = $this->waUser();

        $this->actingAs($user)
            ->post(route('account.setup.update'), [
                'username' => 'taken',
                'email' => 'fresh@example.com',
                'password' => 'a-strong-password',
                'password_confirmation' => 'a-strong-password',
            ])
            ->assertSessionHasErrors('username');

        $this->assertTrue($user->fresh()->needsCredentialsSetup());
    }

    public function test_mismatched_password_confirmation_is_rejected(): void
    {
        $user = $this->waUser();

        $this->actingAs($user)
            ->post(route('account.setup.update'), [
                'username' => 'freshname',
                'email' => 'fresh@example.com',
                'password' => 'a-strong-password',
                'password_confirmation' => 'does-not-match',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_skipping_leaves_the_flag_set_and_goes_to_the_dashboard(): void
    {
        $user = $this->waUser();

        $this->actingAs($user)
            ->post(route('account.setup.skip'))
            ->assertRedirect(route('dashboard'));

        $this->assertTrue($user->fresh()->needsCredentialsSetup());
    }

    public function test_admins_land_on_the_admin_dashboard_after_saving(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin', 'is_active' => true, 'credentials_set_at' => null,
        ]);

        $this->actingAs($admin)
            ->post(route('account.setup.update'), [
                'username' => 'adminname',
                'email' => 'admin-real@example.com',
                'password' => 'a-strong-password',
                'password_confirmation' => 'a-strong-password',
            ])
            ->assertRedirect(route('admin.dashboard'));
    }
}
