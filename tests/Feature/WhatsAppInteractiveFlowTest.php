<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use App\WhatsApp\AI\AIGuard;
use App\WhatsApp\AI\GeminiProvider;
use App\WhatsApp\Flow\FlowEngine;
use App\WhatsApp\Intent\IntentEngine;
use App\WhatsApp\Session\SessionContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class WhatsAppInteractiveFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makeService(string $category, string $name, float $rate = 1.0): Service
    {
        return Service::create([
            'name' => $name,
            'name_sn' => $name,
            'description' => '',
            'description_sn' => '',
            'category' => $category,
            'type' => 'default',
            'rate' => $rate,
            'min_qty' => 100,
            'max_qty' => 10000,
            'is_active' => true,
        ]);
    }

    public function test_order_flow_emits_interactive_lists_and_confirm_buttons(): void
    {
        $this->makeService('Instagram', 'Instagram Followers', 2.0);
        $this->makeService('TikTok', 'TikTok Views', 0.5);

        $user = User::factory()->create(['balance' => 100]);
        $ctx = new SessionContext('263771234567');
        $ctx->set('_user_id', $user->id);

        $engine = app(FlowEngine::class);

        // Category pick is an interactive list with fs:N row ids.
        $res = $engine->start($ctx, 'order');
        $this->assertNotNull($res->list);
        $this->assertSame('fs:1', $res->list['sections'][0]['rows'][0]['id']);

        // Service pick is also a list.
        $res = $engine->advance($ctx, '1'); // Instagram
        $this->assertNotNull($res->list);
        $this->assertStringContainsString('$2.00/1k', $res->list['sections'][0]['rows'][0]['description']);

        // Link + quantity stay free text.
        $res = $engine->advance($ctx, '1');
        $this->assertNull($res->list);
        $res = $engine->advance($ctx, 'https://instagram.com/jane');
        $res = $engine->advance($ctx, '1000');

        // Confirm step carries Yes/Cancel buttons.
        $this->assertNotNull($res->buttons);
        $this->assertSame('fs:yes', $res->buttons[0]['id']);
        $this->assertSame('confirm', $ctx->state);
    }

    public function test_order_confirm_offers_deposit_button_when_balance_too_low(): void
    {
        $this->makeService('Instagram', 'Instagram Followers', 5.0);

        $user = User::factory()->create(['balance' => 0]);
        $ctx = new SessionContext('263771234567');
        $ctx->set('_user_id', $user->id);

        $engine = app(FlowEngine::class);
        $engine->start($ctx, 'order');
        $engine->advance($ctx, '1');
        $engine->advance($ctx, '1');
        $engine->advance($ctx, 'https://instagram.com/jane');
        $res = $engine->advance($ctx, '1000');

        $ids = array_column($res->buttons ?? [], 'id');
        $this->assertContains('fl_deposit', $ids);
    }

    /**
     * Regression: buildContext() used to clobber its string $query param with an
     * Eloquent builder and pass it to KnowledgeBase::search(string) — a TypeError
     * on every free-text message, which silently killed the whole AI path.
     */
    public function test_gemini_provider_respond_does_not_throw(): void
    {
        $this->makeService('Instagram', 'Instagram Followers');

        // No API key configured → must return null gracefully, never throw.
        config(['services.gemini.api_key' => null]);

        $res = app(GeminiProvider::class)->respond('how do refills work?', [
            'user' => null,
            'authenticated' => false,
            'history' => [],
        ]);

        $this->assertNull($res);
    }

    public function test_ai_failure_degrades_and_does_not_burn_daily_budget(): void
    {
        $ai = Mockery::mock(GeminiProvider::class);
        $ai->shouldReceive('isConfigured')->andReturn(true);
        $ai->shouldReceive('respond')->andThrow(new \RuntimeException('gemini down'));

        $engine = new IntentEngine($ai, app(AIGuard::class));
        $res = $engine->resolve('hello', '263771234567', ['user' => null, 'authenticated' => false, 'history' => []]);

        $this->assertFalse($res['handled']);
        $this->assertSame(0, (int) Cache::get('wa:ai:263771234567:'.now()->format('Y-m-d'), 0));
    }
}
