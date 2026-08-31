<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\UserResource;
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

    public function test_admin_can_create_an_agent(): void
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
        ]);
    }
}
