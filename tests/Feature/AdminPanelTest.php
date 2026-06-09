<?php

namespace Tests\Feature;

use App\Filament\Resources\TournamentResource\Pages\ListTournaments;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\Season2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(Season2026Seeder::class);
    }

    public function test_admin_requires_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk();
    }

    public function test_super_admin_can_list_tournaments(): void
    {
        $admin = User::where('role', User::ROLE_SUPER_ADMIN)->firstOrFail();

        // The list page shell renders for an authorized user...
        $this->actingAs($admin)->get('/admin/tournaments')->assertOk();

        // ...and the Livewire table actually contains the seeded catalog.
        Livewire::actingAs($admin);
        Livewire::test(ListTournaments::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords(Tournament::orderBy('starts_on')->take(10)->get());
    }

    public function test_parent_cannot_access_admin(): void
    {
        $parent = User::factory()->create(['role' => User::ROLE_PARENT]);

        $this->actingAs($parent)->get('/admin')->assertForbidden();
    }
}
