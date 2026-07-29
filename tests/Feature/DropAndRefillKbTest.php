<?php

namespace Tests\Feature;

use App\Models\WhatsAppKnowledge;
use App\WhatsApp\Intent\KnowledgeBase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "My followers are dropping" is the most common complaint after delivery, and
 * a customer who doesn't hear why assumes they were sold fakes. The answer has
 * to reach them on the phrasings they actually use.
 */
class DropAndRefillKbTest extends TestCase
{
    use RefreshDatabase;

    public function test_drop_questions_find_the_refill_answer(): void
    {
        $kb = app(KnowledgeBase::class);

        $phrasings = [
            'my followers are dropping',
            'why did my likes go down',
            'i lost followers',
            'views went down',
            'the followers i bought are falling',
            'my count is reducing',
        ];

        foreach ($phrasings as $question) {
            $titles = collect($kb->search($question, 3))->pluck('title')->all();
            $this->assertContains('Refill guarantee', $titles, "no refill answer for: {$question}");
        }
    }

    public function test_the_answer_explains_why_and_promises_a_timeframe(): void
    {
        $answer = (string) WhatsAppKnowledge::where('title', 'Refill guarantee')->value('answer');

        // Why it happens — without this the customer assumes they bought fakes.
        $this->assertStringContainsStringIgnoringCase('detection systems', $answer);
        // That it's harmless to them.
        $this->assertStringContainsStringIgnoringCase('does *not* affect your profile', $answer);
        // And what we'll actually do, by when.
        $this->assertStringContainsStringIgnoringCase('refill', $answer);
        $this->assertStringContainsString('72 hours', $answer);
    }

    public function test_there_is_only_one_entry_on_the_subject(): void
    {
        // Two competing entries split the search and the assistant quotes
        // whichever happens to rank higher.
        $this->assertSame(
            1,
            WhatsAppKnowledge::where('title', 'like', '%refill%')
                ->orWhere('title', 'like', '%dropped%')
                ->count()
        );
    }
}
