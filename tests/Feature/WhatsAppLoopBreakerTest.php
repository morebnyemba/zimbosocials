<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessage;
use App\Services\AI\GeminiClient;
use App\WhatsApp\Routing\MessageRouter;
use App\WhatsApp\Session\SessionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

/**
 * A live conversation died like this: the assistant asked "please send the
 * link" five times while the customer kept answering, then gave up and told us
 * to take payment manually.
 *
 * Repeating a question is worse than getting it wrong once — the customer
 * concludes the bot is broken. When the model repeats itself we take over: hand
 * to the deterministic order flow if we know what they're buying, otherwise get
 * a human in.
 */
class WhatsAppLoopBreakerTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '263771234567';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.whatsapp.api_token' => 't',
            'services.whatsapp.phone_number_id' => '1',
            'services.gemini.api_key' => 'k',
        ]);
        // A fresh id per send — wa_message_id is unique, and these tests send
        // several messages per conversation.
        Http::fake(fn () => Http::response(['messages' => [['id' => 'wamid.'.uniqid('', true)]]]));
    }

    private function linkedAccount(): User
    {
        $user = User::factory()->create(['balance' => 0]);
        WhatsAppAccount::create([
            'wa_phone' => self::PHONE, 'user_id' => $user->id,
            'link_status' => 'linked', 'opted_in' => true,
        ]);

        return $user;
    }

    /** The model stuck on one sentence, exactly as it happened live. */
    private function brokenRecordAi(array $flowData = []): void
    {
        $mock = Mockery::mock(GeminiClient::class);
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('generateJson')->andReturn([
            'reply' => 'Please send the *link* for your TikTok Followers order.',
            'follow_up' => null,
            'flow' => 'none',
            'flow_data' => $flowData,
        ]);
        $this->app->instance(GeminiClient::class, $mock);
    }

    private function say(string $text): void
    {
        app(MessageRouter::class)->handle([
            'from' => self::PHONE, 'wa_message_id' => 'wamid.'.uniqid('', true), 'type' => 'text',
            'text' => $text, 'interactive_id' => null, 'name' => 'Takudzwa', 'timestamp' => time(), 'raw' => [],
        ]);
    }

    public function test_a_loop_with_everything_gathered_jumps_to_the_confirmation(): void
    {
        $user = $this->linkedAccount();
        $user->forceFill(['balance' => 100])->save();
        $service = Service::create([
            'name' => 'TikTok Followers', 'name_sn' => 'x', 'description' => '', 'description_sn' => '',
            'category' => 'TikTok', 'type' => 'followers', 'rate' => 10.0,
            'min_qty' => 10, 'max_qty' => 1000000, 'is_active' => true,
        ]);
        // The model has it all — it's just stuck repeating instead of moving on.
        $this->brokenRecordAi([
            'service_id' => $service->id,
            'link' => 'https://www.tiktok.com/@dubie934',
            'quantity' => 1000,
        ]);

        $this->say('tiktok');
        $this->say('dubie934');
        $this->say('1000');
        $this->say('hello');   // 4th similar reply — only now do we step in

        // Straight to the price. The flow asks NOTHING; collection was the AI's job.
        $ctx = app(SessionManager::class)->load(self::PHONE);
        $this->assertSame('confirm', $ctx->state);

        $out = WhatsAppMessage::where('direction', 'out')->latest('id')->first();
        $this->assertStringContainsString('Confirm your order', (string) $out->body);
        $this->assertStringContainsString('dubie934', (string) $out->body);
    }

    public function test_a_repeat_nudges_the_team_without_silencing_the_bot(): void
    {
        $this->linkedAccount();
        User::factory()->create(['role' => 'admin', 'is_active' => true, 'whatsapp_number' => '263770000001']);
        $this->brokenRecordAi();   // nothing gathered — no flow to hand to

        $this->say('hi');
        $this->say('i said tiktok');
        $this->say('are you listening');
        $this->say('hello?');      // 4th similar reply

        // Silencing the bot is the expensive move: if nobody is watching, the
        // customer gets nothing at all. So the team is told and the assistant
        // keeps going.
        $account = WhatsAppAccount::where('wa_phone', self::PHONE)->first();
        $this->assertFalse($account->inAgentHandoff(), 'a first repeat must not silence the bot');
        $this->assertNotNull(
            \App\Models\Notification::where('type', 'admin_whatsapp_nudge')->first(),
            'the team should have been nudged'
        );

        // And the customer still got an answer.
        $out = WhatsAppMessage::where('direction', 'out')->where('wa_phone', self::PHONE)->latest('id')->first();
        $this->assertStringContainsString('link', (string) $out->body);
    }

    public function test_a_loop_that_keeps_going_eventually_hands_over(): void
    {
        $this->linkedAccount();
        $this->brokenRecordAi();

        foreach (['hi', 'i said tiktok', 'are you listening', 'hello?', 'anyone there', 'this is useless'] as $said) {
            $this->say($said);
        }

        // Nudged once, still circling — now interrupting is the kinder option.
        $account = WhatsAppAccount::where('wa_phone', self::PHONE)->first();
        $this->assertTrue($account->inAgentHandoff());

        $out = WhatsAppMessage::where('direction', 'out')->where('wa_phone', self::PHONE)->latest('id')->first();
        $this->assertStringContainsString('going in circles', (string) $out->body);
    }

    /**
     * The live failure this was written for: the model asked the same either/or
     * three times, reworded each time, while the customer answered "Okay" then
     * "Yaa". Verbatim matching missed it completely.
     */
    public function test_a_reworded_repeat_of_the_same_question_is_caught(): void
    {
        $this->linkedAccount();

        $rewordings = [
            'Sharp, growing your brand is key! For a timber business, we can help you reach new customers directly through sponsored adverts on Facebook and Instagram. We can also boost your followers to build trust and credibility for your brand. Which sounds like a better first step for you?',
            'Sharp! So, would you like to start with sponsored adverts to reach new customers, or grow your followers to build brand credibility first? Let me know which one you would prefer!',
            'Great! Which one are you leaning towards — sponsored adverts to find new customers, or growing your followers to build your brand trust? Let me know!',
        ];
        $i = 0;
        $mock = Mockery::mock(GeminiClient::class);
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('generateJson')->andReturnUsing(function () use ($rewordings, &$i) {
            return ['reply' => $rewordings[min($i++, 2)], 'follow_up' => null, 'flow' => 'none', 'flow_data' => []];
        });
        $this->app->instance(GeminiClient::class, $mock);

        User::factory()->create(['role' => 'admin', 'is_active' => true, 'whatsapp_number' => '263770000001']);

        $this->say('To grow my brand');
        $this->say('Okay');
        $this->say('Yaa');
        $this->say('mmm');

        // Detection is the point here: reworded repeats must register as a loop
        // at all, which verbatim matching missed entirely.
        $this->assertNotNull(
            \App\Models\Notification::where('type', 'admin_whatsapp_nudge')->first(),
            'a reworded repeat must still register as a loop'
        );
    }

    public function test_two_different_replies_are_not_a_loop(): void
    {
        $this->linkedAccount();

        $replies = ['What would you like to grow?', 'Great — how many followers?', 'And your username?'];
        $i = 0;
        $mock = Mockery::mock(GeminiClient::class);
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('generateJson')->andReturnUsing(function () use ($replies, &$i) {
            return ['reply' => $replies[$i++ % 3], 'follow_up' => null, 'flow' => 'none', 'flow_data' => []];
        });
        $this->app->instance(GeminiClient::class, $mock);

        $this->say('hi');
        $this->say('tiktok');
        $this->say('1000');

        // Normal conversation is untouched — no handoff, no forced flow.
        $account = WhatsAppAccount::where('wa_phone', self::PHONE)->first();
        $this->assertFalse($account->inAgentHandoff());
        $this->assertNull(app(SessionManager::class)->load(self::PHONE)->flow);
    }

    public function test_asking_the_same_thing_twice_is_still_allowed(): void
    {
        $this->linkedAccount();
        $this->brokenRecordAi();

        $this->say('hi');
        $this->say('what?');

        // A genuine re-ask is normal; we only step in on the third.
        $account = WhatsAppAccount::where('wa_phone', self::PHONE)->first();
        $this->assertFalse($account->inAgentHandoff());
    }
}
