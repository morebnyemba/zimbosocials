<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsAppTemplate;
use App\Providers\AppServiceProvider;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class WhatsAppTemplateAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    public function test_migration_seeds_templates_from_config(): void
    {
        $this->assertGreaterThan(0, WhatsAppTemplate::count());
        $this->assertDatabaseHas('whatsapp_templates', ['name' => 'welcome_message', 'category' => 'UTILITY']);
    }

    public function test_admin_can_create_a_template(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.whatsapp.templates.store'), [
                'name' => 'order_delivered',
                'category' => 'UTILITY',
                'body' => 'Hi {{1}}, your order #{{2}} was delivered! 🎉',
                'params' => ['user_name', 'order_id'],
                'footer' => 'Zimbo Socials',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('whatsapp_templates', ['name' => 'order_delivered']);
    }

    public function test_invalid_names_and_gapped_placeholders_are_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.whatsapp.templates.store'), [
                'name' => 'Bad Name!', 'category' => 'UTILITY', 'body' => 'x',
            ])
            ->assertSessionHasErrors('name');

        $this->actingAs($admin)
            ->post(route('admin.whatsapp.templates.store'), [
                'name' => 'gapped', 'category' => 'UTILITY', 'body' => 'Hi {{1}} and {{3}}',
            ])
            ->assertSessionHasErrors('body');
    }

    public function test_editing_updates_the_boot_config_override(): void
    {
        $template = WhatsAppTemplate::where('name', 'welcome_message')->firstOrFail();

        $this->actingAs($this->admin())
            ->put(route('admin.whatsapp.templates.update', $template), [
                'category' => 'UTILITY',
                'body' => 'Mauya {{1}}! Account yenyu yagadzirwa. 🎉',
                'params' => ['user_name'],
            ])
            ->assertRedirect();

        // Re-run the boot override (fresh cache) and confirm consumers see the edit.
        Cache::forget('wa:templates:config');
        $provider = new AppServiceProvider(app());
        (new \ReflectionMethod($provider, 'loadWhatsAppTemplates'))->invoke($provider);

        $this->assertSame(
            'Mauya {{1}}! Account yenyu yagadzirwa. 🎉',
            config('whatsapp-templates.templates.welcome_message.body')
        );
    }

    public function test_push_submits_template_to_meta(): void
    {
        $template = WhatsAppTemplate::where('name', 'welcome_message')->firstOrFail();

        $mock = Mockery::mock(WhatsAppService::class);
        $mock->shouldReceive('listTemplates')->andReturn(['ok' => true, 'templates' => []]);
        $mock->shouldReceive('createTemplate')
            ->withArgs(function (array $payload) {
                $body = collect($payload['components'])->firstWhere('type', 'BODY');

                // Meta rejects variable templates without sample values — the
                // body has {{1}}, so example.body_text must carry one sample.
                return $payload['name'] === 'welcome_message'
                    && $body !== null
                    && count($body['example']['body_text'][0] ?? []) === 1;
            })
            ->andReturn(['ok' => true]);
        $this->app->instance(WhatsAppService::class, $mock);

        $this->actingAs($this->admin())
            ->post(route('admin.whatsapp.templates.push', $template))
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_push_refuses_when_template_approved_on_meta(): void
    {
        $template = WhatsAppTemplate::where('name', 'welcome_message')->firstOrFail();

        $mock = Mockery::mock(WhatsAppService::class);
        $mock->shouldReceive('listTemplates')->andReturn([
            'ok' => true,
            'templates' => [['id' => '111', 'name' => 'welcome_message', 'status' => 'APPROVED']],
        ]);
        $mock->shouldNotReceive('createTemplate');
        $mock->shouldNotReceive('updateTemplate');
        $this->app->instance(WhatsAppService::class, $mock);

        $this->actingAs($this->admin())
            ->post(route('admin.whatsapp.templates.push', $template))
            ->assertSessionHas('error');
    }

    public function test_push_resubmits_rejected_template_via_update(): void
    {
        $template = WhatsAppTemplate::where('name', 'welcome_message')->firstOrFail();

        $mock = Mockery::mock(WhatsAppService::class);
        $mock->shouldReceive('listTemplates')->andReturn([
            'ok' => true,
            'templates' => [['id' => '222', 'name' => 'welcome_message', 'status' => 'REJECTED']],
        ]);
        $mock->shouldNotReceive('createTemplate');
        $mock->shouldReceive('updateTemplate')
            ->withArgs(fn (string $id, array $payload) => $id === '222'
                && collect($payload['components'])->firstWhere('type', 'BODY') !== null)
            ->andReturn(['ok' => true]);
        $this->app->instance(WhatsAppService::class, $mock);

        $this->actingAs($this->admin())
            ->post(route('admin.whatsapp.templates.push', $template))
            ->assertSessionHas('success');
    }

    public function test_sync_command_resubmits_rejected_templates(): void
    {
        // Keep one known template; remote reports it REJECTED.
        WhatsAppTemplate::where('name', '!=', 'welcome_message')->delete();
        Cache::forget('wa:templates:config');
        (new \ReflectionMethod($p = new AppServiceProvider(app()), 'loadWhatsAppTemplates'))->invoke($p);

        $mock = Mockery::mock(WhatsAppService::class);
        $mock->shouldReceive('listTemplates')->andReturn([
            'ok' => true,
            'templates' => [['id' => '333', 'name' => 'welcome_message', 'status' => 'REJECTED']],
        ]);
        $mock->shouldNotReceive('createTemplate');
        $mock->shouldReceive('updateTemplate')->once()
            ->withArgs(fn (string $id) => $id === '333')
            ->andReturn(['ok' => true]);
        $this->app->instance(WhatsAppService::class, $mock);

        $this->artisan('whatsapp:sync-templates')
            ->expectsOutputToContain('Resubmitted')
            ->assertSuccessful();
    }

    public function test_meta_payload_samples_match_placeholder_count(): void
    {
        // deposit_confirmed has 4 placeholders: user_name, amount, new_balance, date.
        $tpl = WhatsAppTemplate::where('name', 'deposit_confirmed')->firstOrFail();
        $payload = WhatsAppTemplate::metaPayload($tpl->name, $tpl->toConfigShape(), 'en');
        $body = collect($payload['components'])->firstWhere('type', 'BODY');

        $this->assertCount(4, $body['example']['body_text'][0]);
        $this->assertSame('Tendai Moyo', $body['example']['body_text'][0][0]);

        // A no-variable template must NOT carry an example block.
        $noVars = WhatsAppTemplate::metaPayload('static', ['body' => 'Hello there!', 'params' => []], 'en');
        $this->assertArrayNotHasKey('example', collect($noVars['components'])->firstWhere('type', 'BODY'));
    }

    public function test_non_admins_cannot_manage_templates(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)
            ->post(route('admin.whatsapp.templates.store'), [
                'name' => 'sneaky', 'category' => 'UTILITY', 'body' => 'x',
            ])
            ->assertForbidden();
    }
}
