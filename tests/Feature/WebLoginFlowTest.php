<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WebLoginToken;
use App\WhatsApp\Flow\FlowEngine;
use App\WhatsApp\Session\SessionContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * The 'weblogin' WhatsApp flow mints a one-tap link into the website; these
 * cover both the issuing side (the flow) and the consuming side (the route
 * a tap on that link hits).
 */
class WebLoginFlowTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '263771234567';

    private function start(User $user): SessionContext
    {
        $ctx = new SessionContext(self::PHONE);
        $ctx->set('_user_id', $user->id);
        app(FlowEngine::class)->start($ctx, 'weblogin');

        return $ctx;
    }

    public function test_the_flow_sends_a_working_login_link(): void
    {
        $user = User::factory()->create();
        $this->start($user);

        $this->assertDatabaseCount('web_login_tokens', 1);
    }

    public function test_a_guest_session_cannot_mint_a_token(): void
    {
        $ctx = new SessionContext(self::PHONE);
        $res = app(FlowEngine::class)->start($ctx, 'weblogin');

        $this->assertStringContainsString('verify', (string) $res->reply);
        $this->assertDatabaseCount('web_login_tokens', 0);
    }

    public function test_issuing_twice_in_quick_succession_is_rate_limited(): void
    {
        $user = User::factory()->create();
        $ctx = new SessionContext(self::PHONE);
        $ctx->set('_user_id', $user->id);
        $engine = app(FlowEngine::class);

        $engine->start($ctx, 'weblogin');
        $engine->start($ctx, 'weblogin');

        // Only the first call actually minted a token — the second was throttled.
        $this->assertDatabaseCount('web_login_tokens', 1);
    }

    public function test_a_fresh_token_logs_the_customer_in(): void
    {
        $user = User::factory()->create();
        $raw = WebLoginToken::issue($user->id, self::PHONE);

        $this->get('/wa-login/'.$raw)->assertRedirect(route('dashboard'));

        $this->assertTrue(Auth::check());
        $this->assertSame($user->id, Auth::id());
    }

    public function test_a_user_still_on_auto_generated_credentials_is_routed_to_setup_first(): void
    {
        $user = User::factory()->create(['credentials_set_at' => null]);
        $raw = WebLoginToken::issue($user->id, self::PHONE);

        $this->get('/wa-login/'.$raw)->assertRedirect(route('account.setup.edit'));

        $this->assertTrue(Auth::check());
    }

    public function test_the_token_cannot_be_used_twice(): void
    {
        $user = User::factory()->create();
        $raw = WebLoginToken::issue($user->id, self::PHONE);

        $this->get('/wa-login/'.$raw);
        Auth::logout();

        $this->get('/wa-login/'.$raw)
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors();

        $this->assertFalse(Auth::check());
    }

    public function test_an_expired_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $raw = WebLoginToken::issue($user->id, self::PHONE);
        WebLoginToken::first()->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->get('/wa-login/'.$raw)
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors();

        $this->assertFalse(Auth::check());
    }

    public function test_a_bogus_token_is_rejected(): void
    {
        $this->get('/wa-login/not-a-real-token')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors();
    }

    public function test_an_admin_lands_on_the_admin_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $raw = WebLoginToken::issue($admin->id, self::PHONE);

        $this->get('/wa-login/'.$raw)->assertRedirect(route('admin.dashboard'));
    }
}
