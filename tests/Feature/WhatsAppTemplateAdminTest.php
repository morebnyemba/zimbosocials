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
                return $payload['name'] === 'welcome_message'
                    && collect($payload['components'])->contains(fn ($c) => $c['type'] === 'BODY');
            })
            ->andReturn(['ok' => true]);
        $this->app->instance(WhatsAppService::class, $mock);

        $this->actingAs($this->admin())
            ->post(route('admin.whatsapp.templates.push', $template))
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_push_refuses_when_template_already_on_meta(): void
    {
        $template = WhatsAppTemplate::where('name', 'welcome_message')->firstOrFail();

        $mock = Mockery::mock(WhatsAppService::class);
        $mock->shouldReceive('listTemplates')->andReturn([
            'ok' => true,
            'templates' => [['name' => 'welcome_message', 'status' => 'APPROVED']],
        ]);
        $mock->shouldNotReceive('createTemplate');
        $this->app->instance(WhatsAppService::class, $mock);

        $this->actingAs($this->admin())
            ->post(route('admin.whatsapp.templates.push', $template))
            ->assertSessionHas('error');
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
