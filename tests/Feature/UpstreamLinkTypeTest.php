<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Service;
use App\Models\ServiceUpstream;
use App\Models\UpstreamProvider;
use App\Models\User;
use App\Services\Upstream\LinkFormatter;
use App\Services\Upstream\OrderDispatchService;
use App\Services\Upstream\UpstreamProviderClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Some upstreams want a bare username, not a URL. The customer always pastes a
 * link; we convert it per the mapping's link_type at dispatch. Defaults to
 * 'url' (send as-is) so nothing changes until a service is tagged.
 */
class UpstreamLinkTypeTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\DataProvider('usernameLinks')]
    public function test_username_is_extracted_from_a_profile_url(string $link, string $expected): void
    {
        $this->assertSame($expected, LinkFormatter::toUsername($link));
    }

    public static function usernameLinks(): array
    {
        return [
            'tiktok @'      => ['https://www.tiktok.com/@jane', 'jane'],
            'tiktok trailing' => ['https://tiktok.com/@jane/', 'jane'],
            'instagram'     => ['https://instagram.com/jane', 'jane'],
            'youtube channel' => ['https://youtube.com/channel/UC123', 'UC123'],
            'youtube @'     => ['https://youtube.com/@janedoe', 'janedoe'],
            'bare handle'   => ['@jane', 'jane'],
            'bare word'     => ['jane', 'jane'],
            'query stripped' => ['https://instagram.com/jane?hl=en', 'jane'],
        ];
    }

    public function test_url_type_sends_the_link_unchanged(): void
    {
        $this->assertSame('https://tiktok.com/@jane', LinkFormatter::forUpstream('https://tiktok.com/@jane', 'url'));
        // Unknown/blank type is treated as url — never mangled.
        $this->assertSame('https://tiktok.com/@jane', LinkFormatter::forUpstream('https://tiktok.com/@jane', null));
    }

    public function test_infer_flags_username_only_on_a_clear_signal(): void
    {
        $this->assertSame('username', LinkFormatter::infer('TikTok Followers [Username]'));
        $this->assertSame('username', LinkFormatter::infer('IG Followers', 'Link: username only, no @'));
        $this->assertSame('url', LinkFormatter::infer('TikTok Followers', 'Send the profile link'));
        $this->assertSame('url', LinkFormatter::infer('Instagram Likes')); // default
    }

    public function test_dispatch_sends_the_username_when_the_upstream_wants_it(): void
    {
        $sentLink = null;
        Http::fake(function ($request) use (&$sentLink) {
            $sentLink = $request['link'] ?? null;

            return Http::response(['order' => 555]);
        });

        $provider = UpstreamProvider::create([
            'name' => 'P', 'url' => 'https://panel.test/api', 'api_key' => 'k', 'is_active' => true, 'balance' => 100,
        ]);
        $service = Service::create([
            'name' => 'TikTok Followers', 'name_sn' => 'x', 'description' => '', 'description_sn' => '',
            'category' => 'TikTok', 'type' => 'followers', 'rate' => 5.0,
            'min_qty' => 10, 'max_qty' => 100000, 'is_active' => true,
        ]);
        ServiceUpstream::create([
            'service_id' => $service->id, 'upstream_provider_id' => $provider->id,
            'external_service_id' => 'EXT9', 'link_type' => 'username',
            'external_rate' => 2.0, 'markup_type' => 'percentage', 'markup_value' => 100,
            'priority' => 1, 'is_active' => true,
        ]);

        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id, 'service_id' => $service->id,
            'link' => 'https://www.tiktok.com/@jane', 'quantity' => 1000,
            'charge' => 5.0, 'rate_at_order' => 5.0, 'status' => 'pending',
        ]);

        app(OrderDispatchService::class)->dispatch($order);

        $this->assertSame('jane', $sentLink, 'the upstream should receive the bare username, not the URL');
    }

    public function test_dispatch_sends_the_full_url_by_default(): void
    {
        $sentLink = null;
        Http::fake(function ($request) use (&$sentLink) {
            $sentLink = $request['link'] ?? null;

            return Http::response(['order' => 556]);
        });

        $provider = UpstreamProvider::create([
            'name' => 'P', 'url' => 'https://panel.test/api', 'api_key' => 'k', 'is_active' => true, 'balance' => 100,
        ]);
        $service = Service::create([
            'name' => 'IG Followers', 'name_sn' => 'x', 'description' => '', 'description_sn' => '',
            'category' => 'Instagram', 'type' => 'followers', 'rate' => 5.0,
            'min_qty' => 10, 'max_qty' => 100000, 'is_active' => true,
        ]);
        ServiceUpstream::create([
            'service_id' => $service->id, 'upstream_provider_id' => $provider->id,
            'external_service_id' => 'EXT1', // link_type defaults to 'url'
            'external_rate' => 2.0, 'markup_type' => 'percentage', 'markup_value' => 100,
            'priority' => 1, 'is_active' => true,
        ]);

        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id, 'service_id' => $service->id,
            'link' => 'https://instagram.com/jane', 'quantity' => 1000,
            'charge' => 5.0, 'rate_at_order' => 5.0, 'status' => 'pending',
        ]);

        app(OrderDispatchService::class)->dispatch($order);

        $this->assertSame('https://instagram.com/jane', $sentLink);
    }
}
