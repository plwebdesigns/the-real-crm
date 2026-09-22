<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_super_admin_can_manage_users_at_any_location(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $otherSuperAdmin = User::factory()->superAdmin()->create();
        $agent = User::factory()->create();

        $this->assertTrue($admin->can('viewAny', User::class));
        $this->assertTrue($admin->can('create', User::class));
        $this->assertTrue($admin->can('view', $agent));
        $this->assertTrue($admin->can('update', $agent));
        $this->assertTrue($admin->can('delete', $agent));
        $this->assertTrue($admin->can('update', $otherSuperAdmin));
        $this->assertTrue($admin->can('deleteAny', User::class));
    }

    public function test_location_admin_can_manage_users_at_their_location(): void
    {
        $location = Location::factory()->create();
        $admin = User::factory()->admin()->for($location)->create();
        $agent = User::factory()->for($location)->create();

        $this->assertTrue($admin->can('viewAny', User::class));
        $this->assertTrue($admin->can('create', User::class));
        $this->assertTrue($admin->can('view', $agent));
        $this->assertTrue($admin->can('update', $agent));
        $this->assertTrue($admin->can('delete', $agent));
    }

    public function test_location_admin_cannot_manage_users_at_another_location(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAgent = User::factory()->create();

        $this->assertFalse($admin->can('view', $otherAgent));
        $this->assertFalse($admin->can('update', $otherAgent));
        $this->assertFalse($admin->can('delete', $otherAgent));
    }

    public function test_location_admin_cannot_manage_a_super_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $superAdmin = User::factory()->superAdmin()->create();

        $this->assertFalse($admin->can('view', $superAdmin));
        $this->assertFalse($admin->can('update', $superAdmin));
        $this->assertFalse($admin->can('delete', $superAdmin));
    }

    public function test_agent_cannot_manage_users(): void
    {
        $agent = User::factory()->create();
        $otherAgent = User::factory()->for($agent->location)->create();

        $this->assertFalse($agent->can('viewAny', User::class));
        $this->assertFalse($agent->can('create', User::class));
        $this->assertFalse($agent->can('view', $otherAgent));
        $this->assertFalse($agent->can('update', $otherAgent));
        $this->assertFalse($agent->can('delete', $otherAgent));
        $this->assertFalse($agent->can('deleteAny', User::class));
    }
}
