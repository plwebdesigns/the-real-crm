<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Locations\LocationResource;
use App\Filament\Resources\Locations\Pages\ManageLocations;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LocationResourceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_from_locations(): void
    {
        $this->get(LocationResource::getUrl(isAbsolute: false))->assertRedirect();
    }

    public function test_super_admin_can_view_locations(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(LocationResource::getUrl(isAbsolute: false))
            ->assertOk();
    }

    public function test_location_admin_cannot_view_locations(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(LocationResource::getUrl(isAbsolute: false))
            ->assertForbidden();
    }

    public function test_agent_cannot_view_locations(): void
    {
        $agent = User::factory()->create();

        $this->actingAs($agent)
            ->get(LocationResource::getUrl(isAbsolute: false))
            ->assertForbidden();
    }

    public function test_super_admin_can_create_a_location(): void
    {
        $admin = User::factory()->superAdmin()->create();

        Livewire::actingAs($admin)
            ->test(ManageLocations::class)
            ->callAction('create', [
                'name' => 'Miami Beach',
            ]);

        $this->assertDatabaseHas(Location::class, [
            'name' => 'Miami Beach',
        ]);
    }
}
