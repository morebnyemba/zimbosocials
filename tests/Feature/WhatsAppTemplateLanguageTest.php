<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppNotification;
use App\Models\WhatsAppTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * A template must be SENT under the same language it was REGISTERED under.
 *
 * Meta has no Shona/Ndebele, so those templates are created as English. Sending
 * one as "sn" fails with "template does not exist", the job degrades to a
 * free-form message, and Meta refuses that outside the 24-hour service window —
 * which is exactly how a broadcast ends up reaching only recently-active
 * contacts. This pins the mapping on both ends.
 */
class WhatsAppTemplateLanguageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.whatsapp.api_token' => 't', 'services.whatsapp.phone_number_id' => '1']);
    }

    public function test_unsupported_locales_map_to_english(): void
    {
        $this->assertSame('en', WhatsAppTemplate::metaLanguage('sn'));
        $this->assertSame('en', WhatsAppTemplate::metaLanguage('nd'));
        $this->assertSame('en', WhatsAppTemplate::metaLanguage(null));
        $this->assertSame('en', WhatsAppTemplate::metaLanguage(''));
    }

    public function test_languages_meta_actually_supports_are_kept(): void
    {
        $this->assertSame('en', WhatsAppTemplate::metaLanguage('en'));
        $this->assertSame('pt_BR', WhatsAppTemplate::metaLanguage('pt_BR'));
        $this->assertSame('sw', WhatsAppTemplate::metaLanguage('sw'));
    }

    public function test_registration_and_sending_agree_on_the_language(): void
    {
        $tpl = ['category' => 'MARKETING', 'body' => 'Hi {{1}}', 'params' => ['user_name']];
        $registeredAs = WhatsAppTemplate::metaPayload('marketing_broadcast', $tpl, 'sn')['language'];

        $sentAs = null;
        Http::fake(function ($request) use (&$sentAs) {
            $sentAs = data_get($request->data(), 'template.language.code');

            return Http::response(['messages' => [['id' => 'wamid.x']]]);
        });

        // A Shona-speaking customer — the message text is Shona, the template
        // shell is English.
        (new SendWhatsAppNotification('263771234567', 'marketing_broadcast', 'Subject', 'Body', ['Tendai', 'Subject', 'Body'], 'sn'))
            ->handle(app(\App\Services\WhatsAppService::class));

        $this->assertSame('en', $registeredAs);
        $this->assertSame($registeredAs, $sentAs, 'a template must be sent under the language it was registered as');
    }
}
