<?php

namespace Tests\Feature;

use App\Models\WhatsAppAccount;
use App\Models\WhatsAppKnowledge;
use App\Models\WhatsAppMessage;
use App\WhatsApp\Intent\KnowledgeBase;
use App\WhatsApp\Routing\MessageRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sponsored adverts are a first-class offering: the very first thing a new
 * contact hears mentions them, and the weekly packages are grounded in the
 * knowledge base so the assistant never has to invent a price.
 */
class SponsoredAdvertsTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '263779998888';

    private function msg(string $text): array
    {
        return [
            'from' => self::PHONE,
            'wa_message_id' => 'wamid.'.uniqid('', true),
            'type' => 'text',
            'text' => $text,
            'interactive_id' => null,
            'name' => 'Tarisai',
            'timestamp' => time(),
            'raw' => [],
        ];
    }

    public function test_the_advert_packages_are_seeded_by_migration(): void
    {
        $entry = WhatsAppKnowledge::where('title', 'Sponsored adverts')->first();

        $this->assertNotNull($entry, 'the sponsored adverts KB entry should ship with the migration');
        foreach (['15', '30', '50'] as $price) {
            $this->assertStringContainsString($price, (string) $entry->answer);
        }
        $this->assertTrue((bool) $entry->status);
    }

    public function test_advert_questions_find_the_packages_in_the_knowledge_base(): void
    {
        $kb = app(KnowledgeBase::class);

        foreach (['sponsored adverts', 'how much to advertise on facebook', 'I want more customers ads'] as $query) {
            $hits = $kb->search($query, 3);
            $titles = collect($hits)->pluck('title')->all();
            $this->assertContains('Sponsored adverts', $titles, "KB should surface adverts for: {$query}");
        }
    }

    public function test_first_contact_greeting_mentions_sponsored_adverts(): void
    {
        // Brand-new number sending a plain greeting → deterministic intro.
        app(MessageRouter::class)->handle($this->msg('hi'), 'Tarisai');

        $out = WhatsAppMessage::where('wa_phone', self::PHONE)
            ->where('direction', 'out')->latest('id')->first();

        $this->assertNotNull($out);
        $this->assertStringContainsStringIgnoringCase('sponsored advert', (string) $out->body);
    }

    public function test_first_contact_says_help_is_free_and_names_the_languages(): void
    {
        app(MessageRouter::class)->handle($this->msg('hi'), 'Tarisai');

        $body = (string) WhatsAppMessage::where('wa_phone', self::PHONE)
            ->where('direction', 'out')->latest('id')->first()?->body;

        $this->assertStringContainsStringIgnoringCase('free', $body);
        foreach (['English', 'Shona', 'Ndebele'] as $language) {
            $this->assertStringContainsString($language, $body, "first contact should offer {$language}");
        }
    }

    public function test_first_contact_still_invites_growth_orders_too(): void
    {
        app(MessageRouter::class)->handle($this->msg('hello'), 'Tarisai');

        $out = WhatsAppMessage::where('wa_phone', self::PHONE)
            ->where('direction', 'out')->latest('id')->first();

        $body = (string) $out?->body;
        $this->assertStringContainsStringIgnoringCase('followers', $body);
        // And the contact really was treated as first contact.
        $this->assertTrue(WhatsAppAccount::where('wa_phone', self::PHONE)->exists());
    }
}
