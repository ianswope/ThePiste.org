<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\Season2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_404_is_branded(): void
    {
        $this->get('/definitely-not-a-page')
            ->assertNotFound()
            ->assertSee('THEPISTE')
            ->assertSee('Off the strip');
    }

    public function test_403_is_branded(): void
    {
        $this->seed(Season2026Seeder::class);

        $owner = User::factory()->create();
        $fencer = $owner->fencers()->create([
            'name' => 'Theirs', 'weapon' => 'foil', 'age_group' => 'Junior',
            'rating' => 'C', 'drive_radius_miles' => 450,
        ]);

        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->get(route('fencers.edit', $fencer))
            ->assertForbidden()
            ->assertSee('THEPISTE')
            ->assertSee('Halt!');
    }

    public function test_maintenance_page_is_a_self_contained_static_file(): void
    {
        $html = file_get_contents(public_path('maintenance.html'));

        $this->assertStringContainsString('THEPISTE', $html);
        $this->assertStringContainsString('working on some things', $html);
        // must not depend on built assets or app routes
        $this->assertStringNotContainsString('/build/', $html);
        $this->assertStringNotContainsString('{{', $html);
    }
}
