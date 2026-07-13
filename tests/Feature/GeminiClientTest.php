<?php

namespace Tests\Feature;

use App\Services\AI\GeminiClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiClientTest extends TestCase
{
    public function test_schema_rejection_degrades_to_schemaless_call(): void
    {
        config(['services.gemini.api_key' => 'test-key']);

        // First call (with responseSchema) → 400; the schema-less retry → OK.
        Http::fakeSequence('generativelanguage.googleapis.com/*')
            ->push(['error' => ['message' => 'Invalid JSON payload received. Unknown name "responseSchema"']], 400)
            ->push(['candidates' => [['content' => ['parts' => [['text' => '{"reply":"hi","flow":"none"}']]]]]], 200);
        Http::fake(); // fallback for anything else

        $result = app(GeminiClient::class)->generateJson('prompt', 0.4, schema: ['type' => 'OBJECT'], system: 'sys');

        $this->assertSame('hi', $result['reply']);

        // The retry must have dropped the schema.
        Http::assertSentCount(2);
        $requests = [];
        Http::assertSent(function ($request) use (&$requests) {
            $requests[] = $request->data();

            return true;
        });
        $this->assertArrayHasKey('responseSchema', $requests[0]['generationConfig']);
        $this->assertArrayNotHasKey('responseSchema', $requests[1]['generationConfig']);
    }

    public function test_transport_failure_without_schema_does_not_retry(): void
    {
        config(['services.gemini.api_key' => 'test-key']);

        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'boom']], 500)]);

        $this->assertNull(app(GeminiClient::class)->generateJson('prompt'));
        Http::assertSentCount(1);
    }
}
