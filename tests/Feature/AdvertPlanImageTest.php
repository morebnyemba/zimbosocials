<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The plans-card PNG is fetched directly by Meta's servers when the assistant
 * sends it as a WhatsApp image message, so the route must be public and must
 * always return a real image — never an error page or a redirect to login.
 */
class AdvertPlanImageTest extends TestCase
{
    public function test_the_plans_image_renders_as_a_png(): void
    {
        $response = $this->get(route('advertise.plans-image'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        $this->assertStringStartsWith("\x89PNG", $response->getContent());
    }
}
