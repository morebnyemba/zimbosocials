<?php

namespace Tests\Feature;

use App\Models\User;
use App\WhatsApp\Flow\FlowEngine;
use App\WhatsApp\Session\SessionContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The deposit flow handles money, so its inputs must be read literally. A
 * sentence is NOT an amount: "i want 3k followers on instagram" used to be
 * stripped down to "3" and silently became a $3 deposit, losing the customer's
 * real intent (an order) and asking them for money instead.
 */
class DepositInputParsingTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '263771234567';

    private function atAskAmount(): array
    {
        $user = User::factory()->create(['balance' => 0]);
        $ctx = new SessionContext(self::PHONE);
        $ctx->set('_user_id', $user->id);
        $engine = app(FlowEngine::class);
        $engine->start($ctx, 'deposit');

        return [$engine, $ctx];
    }

    public function test_a_sentence_is_not_treated_as_an_amount(): void
    {
        [$engine, $ctx] = $this->atAskAmount();

        $res = $engine->advance($ctx, 'i want 3k followes on instagram');

        // Retries (which hands it to the AI to re-route) — no amount captured.
        $this->assertTrue($res->isRetry());
        $this->assertSame('ask_amount', $ctx->state);
        $this->assertNull($ctx->get('deposit_amount'));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('validAmounts')]
    public function test_real_amounts_are_accepted(string $input, float $expected): void
    {
        [$engine, $ctx] = $this->atAskAmount();

        $engine->advance($ctx, $input);

        $this->assertSame('choose_method', $ctx->state, "'{$input}' should be read as an amount");
        $this->assertSame($expected, (float) $ctx->get('deposit_amount'));
    }

    public static function validAmounts(): array
    {
        return [
            'bare' => ['5', 5.0],
            'decimal' => ['5.50', 5.5],
            'dollar sign' => ['$10', 10.0],
            'with usd' => ['10 USD', 10.0],
            'padded' => ['  7  ', 7.0],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('useMyNumberPhrases')]
    public function test_words_meaning_use_my_number_are_accepted(string $input): void
    {
        [$engine, $ctx] = $this->atAskAmount();
        $engine->advance($ctx, '5');            // → choose_method
        $engine->advance($ctx, '1');            // EcoCash → ask_phone
        $this->assertSame('ask_phone', $ctx->state);

        $engine->advance($ctx, $input);

        $this->assertSame('confirm', $ctx->state, "'{$input}' should mean 'use my WhatsApp number'");
        $this->assertSame('0771234567', $ctx->get('deposit_phone'));
    }

    public static function useMyNumberPhrases(): array
    {
        return [
            ['okay i will use it'],
            ['ok'],
            ['yes please'],
            ['use my number'],
            ['ehe'],
        ];
    }

    public function test_a_typed_number_still_wins_over_an_affirmative(): void
    {
        [$engine, $ctx] = $this->atAskAmount();
        $engine->advance($ctx, '5');
        $engine->advance($ctx, '1');

        $engine->advance($ctx, 'yes 0779999999');

        $this->assertSame('confirm', $ctx->state);
        $this->assertSame('0779999999', $ctx->get('deposit_phone'));
    }
}
