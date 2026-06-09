<?php

namespace Tests\Feature;

use Database\Seeders\Season2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(Season2026Seeder::class);
    }

    public function test_calendar_renders_with_personalized_tiers(): void
    {
        $response = $this->get('/demo');

        $response->assertOk();
        $response->assertSee('ThePiste');
        $response->assertSee("Farren's Season Calendar", false);

        // Catalog content is present.
        $response->assertSee('GRAFA Third Coast Cup RYC/RJCC/ROC');
        $response->assertSee('January NAC + Junior Olympics');

        // Computed tiers render onto the cards.
        $response->assertSee('data-tier="nac"', false);
        $response->assertSee('data-tier="home"', false);
        $response->assertSee('data-tier="priority"', false);

        // Conflict detection surfaced in the UI.
        $response->assertSee('ranks higher', false);

        // Built Vite asset is linked (manifest resolved).
        $response->assertSee('build/assets/app-', false);
    }

    public function test_stats_reflect_the_demo_fencer(): void
    {
        $response = $this->get('/demo');

        // 4 NACs in the 2026-27 season.
        $response->assertSeeInOrder(['<span class="sn">4</span>', 'NACs'], false);
    }
}
