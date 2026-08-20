<?php

namespace Tests\Unit;

use App\WhatsApp\FollowUp\FollowUpRequestParser;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class FollowUpRequestParserTest extends TestCase
{
    private FollowUpRequestParser $parser;

    private Carbon $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new FollowUpRequestParser;
        $this->now = Carbon::parse('2026-01-01 12:00:00');
    }

    public function test_tomorrow_is_recognised(): void
    {
        $res = $this->parser->parse('can you remind me tomorrow?', $this->now);

        $this->assertNotNull($res);
        $this->assertTrue($this->now->copy()->addDay()->equalTo($res['until']));
    }

    public function test_in_n_days_is_recognised(): void
    {
        $res = $this->parser->parse('please follow up in 3 days', $this->now);

        $this->assertNotNull($res);
        $this->assertTrue($this->now->copy()->addDays(3)->equalTo($res['until']));
    }

    public function test_next_week_is_recognised(): void
    {
        $res = $this->parser->parse('check back next week please', $this->now);

        $this->assertNotNull($res);
        $this->assertTrue($this->now->copy()->addWeek()->equalTo($res['until']));
    }

    public function test_in_n_weeks_is_recognised(): void
    {
        $res = $this->parser->parse('remind me in 2 weeks', $this->now);

        $this->assertNotNull($res);
        $this->assertTrue($this->now->copy()->addWeeks(2)->equalTo($res['until']));
    }

    public function test_a_duration_wins_over_stop_wording(): void
    {
        $res = $this->parser->parse("don't forget to follow up in 3 days", $this->now);

        $this->assertNotNull($res);
        $this->assertTrue($this->now->copy()->addDays(3)->equalTo($res['until']));
    }

    public function test_stop_without_a_duration_pauses_for_a_long_while(): void
    {
        $res = $this->parser->parse('please stop following up for now', $this->now);

        $this->assertNotNull($res);
        $this->assertTrue($this->now->copy()->addDays(180)->equalTo($res['until']));
    }

    public function test_unrelated_text_mentioning_tomorrow_is_not_a_scheduling_request(): void
    {
        $this->assertNull($this->parser->parse("I'll pay tomorrow", $this->now));
    }

    public function test_unrelated_text_mentioning_stop_is_not_a_scheduling_request(): void
    {
        $this->assertNull($this->parser->parse('stop', $this->now));
    }

    public function test_empty_text_is_ignored(): void
    {
        $this->assertNull($this->parser->parse('', $this->now));
    }
}
