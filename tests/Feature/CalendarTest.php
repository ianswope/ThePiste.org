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
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('ThePiste');
        $response->assertSee("Farren's Tournament Calendar", false);

        // Catalog content is present.
        $response->assertSee('GRAFA Third Coast Cup RYC/RJCC/ROC');
        $response->assertSee('January NAC + Junior Olympics');

        // Computed tiers render as card classes.
        $response->assertSee('class="card nac"', false);
        $response->assertSee('class="card home"', false);
        $response->assertSee('class="card priority"', false);

        // Conflict detection surfaced in the UI.
        $response->assertSee('ranks higher', false);

        // Built Vite asset is linked (manifest resolved).
        $response->assertSee('build/assets/app-', false);
    }

    public function test_stats_reflect_the_demo_fencer(): void
    {
        $response = $this->get('/');

        // 4 NACs in the 2026-27 season.
        $response->assertSeeInOrder(['<span class="sn">4</span>', 'NACs'], false);
    }
}
