<?php

namespace Tests\Feature;

use App\Models\WhatsAppAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * WhatsApp profile names are free text — often an email or a phone number.
 * Greeting someone as "Hi megaman_music@gmail.com" reads like spam.
 */
class WhatsAppFriendlyNameTest extends TestCase
{
    use RefreshDatabase;

    private function account(?string $displayName): WhatsAppAccount
    {
        return WhatsAppAccount::create([
            'wa_phone' => '263771234567', 'display_name' => $displayName,
            'link_status' => 'guest', 'opted_in' => true,
        ]);
    }

    public function test_real_names_are_used(): void
    {
        $this->assertSame('Tendai', $this->account('Tendai Moyo')->firstName());
    }

    public function test_emails_phone_numbers_and_handles_are_rejected(): void
    {
        foreach (['megaman_music@gmail.com', '+263 77 123 4567', '0771234567', 'https://t.me/shop', '@handle'] as $junk) {
            $account = $this->account($junk);
            $this->assertNull($account->firstName(), "'{$junk}' should not be used as a name");
            $account->delete();
        }
    }

    public function test_empty_names_are_rejected(): void
    {
        $this->assertNull($this->account(null)->firstName());
    }
}
