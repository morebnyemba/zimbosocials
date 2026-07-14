<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\WhatsApp\Flow\FlowEngine;
use App\WhatsApp\Intent\IntentEngine;
use App\WhatsApp\ReferralNudge;
use App\WhatsApp\Routing\MessageRouter;
use App\WhatsApp\Session\SessionContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class WhatsAppReferralTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '263771234567';

    private function linkedUser(): User
    {
        $user = User::factory()->create(['balance' => 500]);
        WhatsAppAccount::create([
            'wa_phone' => self::PHONE,
            'user_id' => $user->id,
            'link_status' => 'linked',
            'opted_in' => true,
        ]);

        return $user;
    }

    private function msg(string $text): array
    {
        return [
            'from' => self::PHONE, 'wa_message_id' => 'wamid.'.uniqid('', true),
            'type' => 'text', 'text' => $text, 'interactive_id' => null,
            'name' => 'Tester', 'timestamp' => time(), 'raw' => [],
        ];
    }

    public function test_referral_flow_shows_link_rewards_and_starts_cooldown(): void
    {
        $user = $this->linkedUser();

        $ctx = new SessionContext(self::PHONE);
        $ctx->set('_user_id', $user->id);

        $res = app(FlowEngine::class)->start($ctx, 'referral');

        $this->assertStringContainsString('ref='.$user->fresh()->referral_code, (string) $res->reply);
        $this->assertStringContainsString('bonus', (string) $res->reply);
        $this->assertStringContainsString('commission', (string) $res->reply);
        $this->assertFalse(ReferralNudge::allowed(self::PHONE)); // pitch seen → cooldown
    }

    public function test_referral_keyword_works_without_ai(): void
    {
        $this->linkedUser();

        $intent = Mockery::mock(IntentEngine::class);
        $intent->shouldReceive('resolve')->andReturn(['handled' => false]);
        $this->app->instance(IntentEngine::class, $intent);

        app(MessageRouter::class)->handle($this->msg('referral'));

        $this->assertSame(
            1,
            \App\Models\WhatsAppMessage::where('direction', 'out')->where('body', 'like', '%ref=%')->count()
        );
    }

    public function test_order_completion_carries_one_capped_referral_footer(): void
    {
        $user = $this->linkedUser();
        Service::create([
            'name' => 'Instagram Followers', 'name_sn' => 'x', 'description' => '', 'description_sn' => '',
            'category' => 'Instagram', 'type' => 'followers', 'rate' => 1.0,
            'min_qty' => 100, 'max_qty' => 100000, 'is_active' => true,
        ]);

        $engine = app(FlowEngine::class);
        $place = function (string $link) use ($engine, $user) {
            $ctx = new SessionContext(self::PHONE);
            $ctx->set('_user_id', $user->id);
            $engine->start($ctx, 'order');
            $engine->advance($ctx, '1');
            $engine->advance($ctx, '1');
            $engine->advance($ctx, $link);
            $engine->advance($ctx, '1000');

            return $engine->advance($ctx, 'yes');
        };

        // First order: footer present, cooldown starts.
        $first = $place('https://instagram.com/friend_one');
        $this->assertStringContainsString('referral', (string) $first->reply);

        // Second order inside the cooldown: no plug.
        $second = $place('https://instagram.com/friend_two');
        $this->assertStringContainsString('Order placed', (string) $second->reply);
        $this->assertStringNotContainsString('referral', (string) $second->reply);
    }

    public function test_ai_reply_mentioning_referrals_consumes_the_nudge(): void
    {
        $this->linkedUser();

        $intent = Mockery::mock(IntentEngine::class);
        $intent->shouldReceive('resolve')->andReturn([
            'handled' => true,
            'reply' => 'Done! By the way — invite friends and earn with our referral program. 🎁',
            'follow_up' => null,
            'flow' => null,
            'flow_data' => [],
        ]);
        $this->app->instance(IntentEngine::class, $intent);

        $this->assertTrue(ReferralNudge::allowed(self::PHONE));
        app(MessageRouter::class)->handle($this->msg('thanks!'));
        $this->assertFalse(ReferralNudge::allowed(self::PHONE));
    }

    public function test_nudge_permission_is_passed_to_the_ai_context(): void
    {
        $this->linkedUser();

        $captured = [];
        $intent = Mockery::mock(IntentEngine::class);
        $intent->shouldReceive('resolve')
            ->withArgs(function ($text, $phone, $context) use (&$captured) {
                $captured = $context;

                return true;
            })
            ->andReturn(['handled' => false]);
        $this->app->instance(IntentEngine::class, $intent);

        $router = app(MessageRouter::class);
        $router->handle($this->msg('hello there my friend'));
        $this->assertTrue($captured['referral_nudge_allowed']);

        ReferralNudge::mark(self::PHONE);
        $router->handle($this->msg('hello again my friend'));
        $this->assertFalse($captured['referral_nudge_allowed']);
    }
}
