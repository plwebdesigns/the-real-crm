<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_from_agents(): void
    {
        $this->get(UserResource::getUrl(isAbsolute: false))->assertRedirect();
    }

    public function test_admin_can_view_agents(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(UserResource::getUrl(isAbsolute: false))
            ->assertOk();
    }

    public function test_agent_cannot_view_agents_resource(): void
    {
        $agent = User::factory()->create();

        $this->actingAs($agent)
            ->get(UserResource::getUrl(isAbsolute: false))
            ->assertForbidden();
    }

    public function test_admin_can_create_an_agent_at_their_location(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(CreateUser::class)
            ->fillForm([
                'name' => 'Jordan Agent',
                'email' => 'jordan@example.com',
                'password' => 'password',
                'is_admin' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(User::class, [
            'name' => 'Jordan Agent',
            'email' => 'jordan@example.com',
            'is_admin' => false,
            'is_super_admin' => false,
            'location_id' => $admin->location_id,
        ]);
    }

    public function test_location_admin_cannot_create_a_super_admin(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(CreateUser::class)
            ->fillForm([
                'name' => 'Escalated Admin',
                'email' => 'escalated@example.com',
                'password' => 'password',
                'is_admin' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(User::class, [
            'email' => 'escalated@example.com',
            'is_admin' => true,
            'is_super_admin' => false,
            'location_id' => $admin->location_id,
        ]);
    }

    public function test_location_admin_does_not_see_agents_from_another_location(): void
    {
        $miami = Location::factory()->create();
        $boston = Location::factory()->create();
        $admin = User::factory()->admin()->for($miami)->create();
        $localAgent = User::factory()->for($miami)->create();
        $otherAgent = User::factory()->for($boston)->create();

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->assertCanSeeTableRecords([$admin, $localAgent])
            ->assertCanNotSeeTableRecords([$otherAgent]);
    }
}
