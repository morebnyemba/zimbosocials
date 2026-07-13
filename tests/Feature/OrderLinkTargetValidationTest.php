<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use App\Services\OrderService;
use App\Services\Upstream\OrderDispatchService;
use App\WhatsApp\Flow\FlowEngine;
use App\WhatsApp\Session\SessionContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Followers-type services must get a PROFILE link; likes/views-type services
 * must get a POST link. Clear mismatches are blocked before money moves;
 * anything ambiguous passes through untouched.
 */
class OrderLinkTargetValidationTest extends TestCase
{
    use RefreshDatabase;

    private function makeService(string $name, string $category, string $type = 'default'): Service
    {
        return Service::create([
            'name' => $name, 'name_sn' => $name, 'description' => '', 'description_sn' => '',
            'category' => $category, 'type' => $type, 'rate' => 1.0,
            'min_qty' => 100, 'max_qty' => 100000, 'is_active' => true,
        ]);
    }

    private function place(Service $service, string $link): array
    {
        $user = User::factory()->create(['balance' => 500]);

        return app(OrderService::class)->placeOrder(
            $user, $service, $link, 1000, app(OrderDispatchService::class), 'test'
        );
    }

    public function test_followers_service_rejects_post_links(): void
    {
        $service = $this->makeService('Instagram Followers [Real]', 'Instagram', 'followers');

        $res = $this->place($service, 'https://instagram.com/p/Cxyz123/');

        $this->assertFalse($res['ok']);
        $this->assertSame('link', $res['field']);
        $this->assertStringContainsString('profile', $res['error']);
    }

    public function test_followers_service_accepts_profile_links(): void
    {
        $service = $this->makeService('TikTok Followers', 'TikTok', 'followers');

        $this->assertTrue($this->place($service, 'https://tiktok.com/@mamaanitah')['ok']);
    }

    public function test_likes_service_rejects_profile_links(): void
    {
        $service = $this->makeService('Instagram Likes', 'Instagram', 'likes');

        $res = $this->place($service, 'https://instagram.com/mamaanitah');

        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('post', $res['error']);
    }

    public function test_video_views_accept_video_links(): void
    {
        $service = $this->makeService('YouTube Views', 'YouTube', 'views');

        $this->assertTrue($this->place($service, 'https://youtu.be/dQw4w9WgXcQ')['ok']);
        $this->assertTrue($this->place($service, 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')['ok']);
    }

    public function test_subscribers_reject_video_links(): void
    {
        $service = $this->makeService('YouTube Subscribers', 'YouTube', 'subscribers');

        $res = $this->place($service, 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');

        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('profile', $res['error']);
    }

    public function test_ambiguous_services_and_shapes_are_not_policed(): void
    {
        // Story views legitimately take a username link.
        $story = $this->makeService('Instagram Story Views', 'Instagram', 'views');
        $this->assertTrue($this->place($story, 'https://instagram.com/mamaanitah')['ok']);

        // Short/unrecognized link shapes pass through.
        $tiktok = $this->makeService('TikTok Likes', 'TikTok', 'likes');
        $this->assertTrue($this->place($tiktok, 'https://vm.tiktok.com/ZMabc123/')['ok']);

        // Unmapped platforms are untouched.
        $tg = $this->makeService('Telegram Members', 'Telegram', 'members');
        $this->assertTrue($this->place($tg, 'https://t.me/somechannel')['ok']);
    }

    public function test_whatsapp_flow_maps_target_error_back_to_link_step(): void
    {
        $this->makeService('Instagram Followers', 'Instagram', 'followers');
        $user = User::factory()->create(['balance' => 500]);

        $ctx = new SessionContext('263771234567');
        $ctx->set('_user_id', $user->id);
        $engine = app(FlowEngine::class);

        $engine->start($ctx, 'order');
        $engine->advance($ctx, '1');
        $engine->advance($ctx, '1');
        $engine->advance($ctx, 'https://instagram.com/p/Cxyz123/'); // post link for followers
        $engine->advance($ctx, '1000');
        $res = $engine->advance($ctx, 'yes');

        // Not placed; user is sent back to the link step with the reason.
        $this->assertSame('enter_link', $ctx->state);
        $this->assertStringContainsString('profile', (string) $res->reply);
        $this->assertDatabaseCount('orders', 0);
    }
}
