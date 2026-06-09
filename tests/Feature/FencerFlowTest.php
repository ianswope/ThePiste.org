<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\Season2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FencerFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(Season2026Seeder::class);
    }

    public function test_auth_pages_render(): void
    {
        $this->get('/login')->assertOk()->assertSee('Welcome back');
        $this->get('/register')->assertOk()->assertSee('Create your account');
    }

    public function test_guest_sees_landing_page(): void
    {
        $this->get('/')->assertOk()->assertSee('Build your season');
    }

    public function test_sample_calendar_uses_demo_profile(): void
    {
        $this->get('/demo')->assertOk()->assertSee('Sample profile');
    }

    public function test_signed_in_user_is_redirected_off_the_landing_page(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_PARENT]);

        $this->actingAs($user)->get('/')->assertRedirect(route('calendar'));
    }

    public function test_user_without_fencer_is_sent_to_the_builder(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_PARENT]);

        $this->actingAs($user)->get('/season')->assertRedirect(route('fencers.create'));
    }

    public function test_user_can_build_a_profile_and_calendar_personalizes(): void
    {
        Http::fake([
            'api.zippopotam.us/*' => Http::response([
                'places' => [[
                    'latitude' => '41.808', 'longitude' => '-88.011',
                    'place name' => 'Downers Grove', 'state abbreviation' => 'IL',
                ]],
            ], 200),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)->post('/fencers', [
            'name' => 'Test Kid',
            'gender' => 'women',
            'handedness' => 'left',
            'age_group' => 'Cadet',
            'home_zip' => '60515',
            'drive_radius_miles' => 300,
            'goal' => 'earn_b',
            'compete_foil' => '1',
            'rating_foil' => 'D',
            'compete_epee' => '1',
            'rating_epee' => 'E',
            'primary_weapon' => 'foil',
        ])->assertRedirect('/');

        $fencer = $user->fencers()->with('weapons')->first();

        $this->assertNotNull($fencer);
        $this->assertSame('Test Kid', $fencer->name);
        $this->assertSame('left', $fencer->handedness);
        $this->assertSame('foil', $fencer->weapon);      // primary denormalized
        $this->assertSame('D', $fencer->rating);
        $this->assertSame(2, $fencer->weapons->count());
        $this->assertEqualsWithDelta(41.808, $fencer->home_lat, 0.01); // geocoded

        // The calendar now shows this fencer, not the demo.
        $this->actingAs($user)->get('/season')
            ->assertOk()
            ->assertSee('Test Kid')
            ->assertDontSee('Sample profile');
    }
}
