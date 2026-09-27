<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class LeadActivityPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_members_can_manage_activities_for_leads_at_their_location(): void
    {
        $location = Location::factory()->create();
        $user = User::factory()->for($location)->create();
        $activity = LeadActivity::factory()
            ->for(Lead::factory()->recycle($location))
            ->create();

        $this->assertTrue($user->can('viewAny', LeadActivity::class));
        $this->assertTrue($user->can('create', LeadActivity::class));
        $this->assertTrue($user->can('view', $activity));
        $this->assertTrue($user->can('update', $activity));
        $this->assertTrue($user->can('delete', $activity));
    }

    public function test_members_cannot_manage_activities_for_leads_at_another_location(): void
    {
        $user = User::factory()->create();
        $activity = LeadActivity::factory()->create();

        $this->assertFalse($user->can('view', $activity));
        $this->assertFalse($user->can('update', $activity));
        $this->assertFalse($user->can('delete', $activity));
    }

    public function test_super_admin_can_manage_activities_at_any_location(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $activity = LeadActivity::factory()->create();

        $this->assertTrue($admin->can('viewAny', LeadActivity::class));
        $this->assertTrue($admin->can('create', LeadActivity::class));
        $this->assertTrue($admin->can('view', $activity));
        $this->assertTrue($admin->can('update', $activity));
        $this->assertTrue($admin->can('delete', $activity));
    }
}
