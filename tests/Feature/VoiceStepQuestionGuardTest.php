<?php

namespace Tests\Feature;

use App\Models\WhatsAppAccount;
use App\Services\AI\GeminiClient;
use App\WhatsApp\AI\GeminiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * The one-voice pass fuses the AI's draft with the flow step. Seen in
 * production: the draft said "just confirm on the next step" while the flow was
 * actually still ASKING for a quantity — the fusion dropped the question and
 * the customer got a dead end with nothing to reply to, so a human had to
 * rescue the sale. If the step asks something, the message must still ask it.
 */
class VoiceStepQuestionGuardTest extends TestCase
{
    use RefreshDatabase;

    private function providerReturning(string $voiced): GeminiProvider
    {
        $client = Mockery::mock(GeminiClient::class);
        $client->shouldReceive('isConfigured')->andReturn(true);
        $client->shouldReceive('generateText')->andReturn($voiced);
        $this->app->instance(GeminiClient::class, $client);

        return app(GeminiProvider::class);
    }

    public function test_a_fused_message_that_drops_the_question_is_rejected(): void
    {
        // What actually happened: the step asks for a quantity, the fusion
        // promises a confirm step and asks nothing.
        $provider = $this->providerReturning(
            'Awesome! Got the link. ✅ I will set up 1,000,000 Facebook Views for that post. Just confirm on the next step!'
        );

        $voiced = $provider->voiceStep(
            'Awesome! Got the link.',
            '🔢 How many? (min *50*, max *5000000*)',
            'https://facebook.com/p/123',
        );

        $this->assertNull($voiced, 'a fusion that asks nothing must fall back to the scripted step');
    }

    public function test_a_fused_message_that_keeps_the_question_is_used(): void
    {
        $provider = $this->providerReturning(
            'Awesome, got the link! ✅ How many views would you like? (min *50*, max *5,000,000*)'
        );

        $voiced = $provider->voiceStep(
            'Awesome! Got the link.',
            '🔢 How many? (min *50*, max *5000000*)',
            'https://facebook.com/p/123',
        );

        $this->assertNotNull($voiced);
        $this->assertStringContainsString('How many', $voiced);
    }

    public function test_an_instruction_step_counts_as_asking(): void
    {
        // No question mark, but it still requests input.
        $provider = $this->providerReturning('Great choice! Send me the link to your post and we are away. 🚀');

        $voiced = $provider->voiceStep('Great choice!', '🔗 Send the *link* for your order.', 'facebook views');

        $this->assertNotNull($voiced, 'an imperative like "send the link" is a valid ask');
    }
}
