<?php

namespace Tests\Feature;

use App\Models\Service;
use App\WhatsApp\AI\GeminiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A "list your services" reply must never run followers, likes and views
 * together in one numbered list — the customer is shopping for one kind of
 * thing. The catalogue handed to the model is grouped [Platform] → <Type> so
 * it has that structure to mirror.
 */
class ServiceCatalogueGroupingTest extends TestCase
{
    use RefreshDatabase;

    private function service(string $name, string $category, string $type = 'default'): Service
    {
        return Service::create([
            'name' => $name, 'name_sn' => $name, 'description' => '', 'description_sn' => '',
            'category' => $category, 'type' => $type, 'rate' => 1.0,
            'min_qty' => 10, 'max_qty' => 10000, 'is_active' => true,
        ]);
    }

    /** buildContext is private; exercise it the way respond() does. */
    private function context(): string
    {
        $provider = app(GeminiProvider::class);
        $method = new \ReflectionMethod($provider, 'buildContext');

        return (string) $method->invoke($provider, 'list your services', null);
    }

    public function test_catalogue_is_grouped_by_platform_then_type(): void
    {
        $this->service('Facebook Page/Profile Followers', 'Facebook');
        $this->service('Facebook Post Likes', 'Facebook');
        $this->service('Facebook Reels/Videos Views', 'Facebook');
        $this->service('TikTok Followers [HQ Accounts]', 'TikTok');

        $context = $this->context();

        $this->assertStringContainsString('[Facebook]', $context);
        $this->assertStringContainsString('[TikTok]', $context);
        // Each kind gets its own sub-heading under the platform.
        foreach (['<Followers>', '<Likes>', '<Views>'] as $heading) {
            $this->assertStringContainsString($heading, $context, "catalogue should sub-group {$heading}");
        }
    }

    public function test_type_is_inferred_from_the_name_when_the_column_is_default(): void
    {
        // Real catalogue rows mostly carry type='default'.
        $this->service('Whatsapp Channel Members', 'WhatsApp');
        $this->service('YouTube Subscribers', 'YouTube');

        $context = $this->context();

        $this->assertStringContainsString('<Members>', $context);
        $this->assertStringContainsString('<Subscribers>', $context);
    }

    public function test_a_platforms_types_are_kept_apart_not_interleaved(): void
    {
        $this->service('Facebook Post Likes', 'Facebook');
        $this->service('Facebook Page/Profile Followers', 'Facebook');
        $this->service('Facebook - Post Likes [All Types]', 'Facebook');

        $context = $this->context();

        // Both "Likes" services sit under one <Likes> heading — it appears once.
        $this->assertSame(1, substr_count($context, '<Likes>'));
        $this->assertSame(1, substr_count($context, '<Followers>'));
    }
}
