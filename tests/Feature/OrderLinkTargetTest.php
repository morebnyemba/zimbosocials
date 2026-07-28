<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Services\Upstream\LinkTarget;
use App\WhatsApp\Flow\Definitions\CreateOrderFlow;
use App\WhatsApp\Session\SessionContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Followers are delivered to a PAGE; likes and views to a specific POST. Taking
 * the wrong one means the customer pays and receives nothing.
 *
 * Straight from a live conversation: someone ordering 3,000 Facebook followers
 * pasted a /posts/ URL and it was accepted without a word.
 */
class OrderLinkTargetTest extends TestCase
{
    use RefreshDatabase;

    private const POST_LINK = 'https://www.facebook.com/61592099696629/posts/122097465177403323/?substory_index=1340876621509147&app=fbl';

    private const PAGE_LINK = 'https://www.facebook.com/profile.php?id=61592099696629';

    public function test_a_post_url_is_recognised_as_a_post(): void
    {
        $this->assertSame('post', LinkTarget::kindOf(self::POST_LINK));
        $this->assertSame('post', LinkTarget::kindOf('https://instagram.com/p/Cxyz123/'));
        $this->assertSame('post', LinkTarget::kindOf('https://www.tiktok.com/@jane/video/7300000000'));
        $this->assertSame('post', LinkTarget::kindOf('https://youtube.com/watch?v=abc'));
    }

    public function test_a_profile_url_is_recognised_as_a_profile(): void
    {
        $this->assertSame('profile', LinkTarget::kindOf(self::PAGE_LINK));
        $this->assertSame('profile', LinkTarget::kindOf('https://tiktok.com/@reallensotsofficial'));
        $this->assertSame('profile', LinkTarget::kindOf('https://instagram.com/jane'));
    }

    public function test_a_followers_service_rejects_a_post_link(): void
    {
        $msg = LinkTarget::mismatch(self::POST_LINK, 'Facebook Page Followers', 'followers');

        $this->assertNotNull($msg);
        $this->assertStringContainsString('page/profile', $msg);
    }

    public function test_a_likes_service_rejects_a_bare_profile_link(): void
    {
        $msg = LinkTarget::mismatch(self::PAGE_LINK, 'Instagram Likes', 'likes');

        $this->assertNotNull($msg);
        $this->assertStringContainsString('post or video', $msg);
    }

    public function test_matching_links_pass(): void
    {
        $this->assertNull(LinkTarget::mismatch(self::PAGE_LINK, 'Facebook Followers', 'followers'));
        $this->assertNull(LinkTarget::mismatch(self::POST_LINK, 'Facebook Post Likes', 'likes'));
    }

    public function test_an_unrecognised_service_kind_never_blocks_the_order(): void
    {
        // We only block when we're sure — a guess must not stop a paying customer.
        $this->assertNull(LinkTarget::mismatch(self::POST_LINK, 'Spotify Plays Premium', ''));
    }

    public function test_a_page_is_recovered_from_a_post_url(): void
    {
        // The post url already names the page — use it rather than sending them
        // back to hunt for Share → Copy Link.
        $this->assertSame(
            'https://www.facebook.com/profile.php?id=61592099696629',
            LinkTarget::toProfile(self::POST_LINK)
        );
        $this->assertSame('https://www.tiktok.com/@jane', LinkTarget::toProfile('https://www.tiktok.com/@jane/video/7300000000'));
        $this->assertSame('https://x.com/jane', LinkTarget::toProfile('https://x.com/jane/status/1789'));
        $this->assertSame(
            'https://www.facebook.com/marvadesigns',
            LinkTarget::toProfile('https://www.facebook.com/marvadesigns/posts/12345')
        );
        $this->assertSame(
            'https://www.facebook.com/profile.php?id=61592099696629',
            LinkTarget::toProfile('https://www.facebook.com/permalink.php?story_fbid=999&id=61592099696629')
        );
    }

    public function test_a_url_that_names_only_the_post_cannot_be_recovered(): void
    {
        // These carry no owner at all — asking is the only honest option.
        $this->assertNull(LinkTarget::toProfile('https://instagram.com/p/Cxyz123/'));
        $this->assertNull(LinkTarget::toProfile('https://youtube.com/watch?v=abc'));
    }

    public function test_the_order_flow_recovers_the_page_instead_of_rejecting(): void
    {
        $service = Service::create([
            'name' => 'Facebook Page Followers', 'name_sn' => 'x', 'description' => '', 'description_sn' => '',
            'category' => 'Facebook', 'type' => 'followers', 'rate' => 5.0,
            'min_qty' => 100, 'max_qty' => 100000, 'is_active' => true,
        ]);

        $ctx = new SessionContext('263771234567');
        $ctx->set('order_service_id', $service->id);

        $res = app(CreateOrderFlow::class)->handle('enter_link', self::POST_LINK, $ctx);

        // Moves on with the recovered page, and says which one it used.
        $this->assertSame('enter_quantity', $res->nextState);
        $this->assertSame('https://www.facebook.com/profile.php?id=61592099696629', $ctx->get('order_link'));
        $this->assertStringContainsString('61592099696629', $res->reply);
    }

    public function test_an_unrecoverable_post_link_still_asks_again(): void
    {
        $service = Service::create([
            'name' => 'Instagram Followers', 'name_sn' => 'x', 'description' => '', 'description_sn' => '',
            'category' => 'Instagram', 'type' => 'followers', 'rate' => 5.0,
            'min_qty' => 100, 'max_qty' => 100000, 'is_active' => true,
        ]);

        $ctx = new SessionContext('263771234567');
        $ctx->set('order_service_id', $service->id);

        $res = app(CreateOrderFlow::class)->handle('enter_link', 'https://instagram.com/p/Cxyz123/', $ctx);

        $this->assertSame('enter_link', $res->nextState);
        $this->assertStringContainsString('page/profile', $res->reply);
        $this->assertFalse($ctx->has('order_link'));
    }

    public function test_a_bare_handle_becomes_a_url_for_that_platform(): void
    {
        $this->assertSame('https://www.facebook.com/marvadesigns', LinkTarget::fromHandle('marvadesigns', 'Facebook'));
        $this->assertSame('https://www.tiktok.com/@jane', LinkTarget::fromHandle('@jane', 'TikTok'));
        $this->assertSame('https://www.instagram.com/jane_doe', LinkTarget::fromHandle('jane_doe', 'instagram'));
    }

    public function test_a_handle_is_never_guessed_without_a_known_platform(): void
    {
        // Boosting the wrong account spends their money on a stranger.
        $this->assertNull(LinkTarget::fromHandle('marvadesigns', 'Spotify'));
        $this->assertNull(LinkTarget::fromHandle('marva designs', 'Facebook'));  // a name, not a handle
        $this->assertNull(LinkTarget::fromHandle('example.com', 'Facebook'));    // a broken url
    }

    public function test_ordinary_conversation_is_never_treated_as_a_page_name(): void
    {
        // The dangerous case: someone answering at the link step. Turning any of
        // these into a url would buy followers for a stranger's account.
        foreach (['yes', 'ok', 'sharp', 'cancel', 'menu', 'done', 'paid', 'wait', '1000', '2', '@500'] as $said) {
            $this->assertNull(
                LinkTarget::fromHandle($said, 'Instagram'),
                "\"{$said}\" must not become a page link"
            );
        }
    }

    public function test_the_order_flow_accepts_a_typed_username(): void
    {
        $service = Service::create([
            'name' => 'Facebook Page Followers', 'name_sn' => 'x', 'description' => '', 'description_sn' => '',
            'category' => 'Facebook', 'type' => 'followers', 'rate' => 5.0,
            'min_qty' => 100, 'max_qty' => 100000, 'is_active' => true,
        ]);

        $ctx = new SessionContext('263771234567');
        $ctx->set('order_service_id', $service->id);

        // "link ndirikutadza" — they just type the name instead.
        $res = app(CreateOrderFlow::class)->handle('enter_link', 'marvadesigns', $ctx);

        $this->assertSame('enter_quantity', $res->nextState);
        $this->assertSame('https://www.facebook.com/marvadesigns', $ctx->get('order_link'));
    }

    public function test_a_handle_from_the_ai_is_turned_into_a_url(): void
    {
        // e.g. the AI read "@marvadesigns" off a screenshot of their page.
        $service = Service::create([
            'name' => 'Facebook Page Followers', 'name_sn' => 'x', 'description' => '', 'description_sn' => '',
            'category' => 'Facebook', 'type' => 'followers', 'rate' => 5.0,
            'min_qty' => 100, 'max_qty' => 100000, 'is_active' => true,
        ]);

        $ctx = new SessionContext('263771234567');
        $ctx->set('_prefill_service_id', $service->id);
        $ctx->set('_prefill_link', '@marvadesigns');

        app(CreateOrderFlow::class)->prompt('pick_category', $ctx);

        $this->assertSame('https://www.facebook.com/marvadesigns', $ctx->get('order_link'));
    }

    public function test_the_order_flow_accepts_the_page_link(): void
    {
        $service = Service::create([
            'name' => 'Facebook Page Followers', 'name_sn' => 'x', 'description' => '', 'description_sn' => '',
            'category' => 'Facebook', 'type' => 'followers', 'rate' => 5.0,
            'min_qty' => 100, 'max_qty' => 100000, 'is_active' => true,
        ]);

        $ctx = new SessionContext('263771234567');
        $ctx->set('order_service_id', $service->id);

        $res = app(CreateOrderFlow::class)->handle('enter_link', self::PAGE_LINK, $ctx);

        $this->assertSame('enter_quantity', $res->nextState);
        $this->assertTrue($ctx->has('order_link'));
    }
}
