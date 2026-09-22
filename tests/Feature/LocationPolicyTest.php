<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class LocationPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_super_admin_can_manage_locations(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $location = Location::factory()->create();

        $this->assertTrue($admin->can('viewAny', Location::class));
        $this->assertTrue($admin->can('create', Location::class));
        $this->assertTrue($admin->can('update', $location));
        $this->assertTrue($admin->can('delete', $location));
        $this->assertTrue($admin->can('deleteAny', Location::class));
    }

    public function test_location_admin_cannot_manage_locations(): void
    {
        $admin = User::factory()->admin()->create();
        $location = Location::factory()->create();

        $this->assertFalse($admin->can('viewAny', Location::class));
        $this->assertFalse($admin->can('create', Location::class));
        $this->assertFalse($admin->can('update', $location));
        $this->assertFalse($admin->can('delete', $location));
        $this->assertFalse($admin->can('deleteAny', Location::class));
    }

    public function test_agent_cannot_manage_locations(): void
    {
        $agent = User::factory()->create();
        $location = Location::factory()->create();

        $this->assertFalse($agent->can('viewAny', Location::class));
        $this->assertFalse($agent->can('create', Location::class));
        $this->assertFalse($agent->can('update', $location));
        $this->assertFalse($agent->can('delete', $location));
        $this->assertFalse($agent->can('deleteAny', Location::class));
    }
}
