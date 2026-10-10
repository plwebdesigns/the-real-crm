<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SalePolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_assigned_agent_can_manage_their_sale(): void
    {
        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $sale = Sale::factory()->withoutAgents()->recycle($location)->create();
        $sale->agents()->attach($agent, ['commission_percent' => 100]);

        $this->assertTrue($agent->can('viewAny', Sale::class));
        $this->assertTrue($agent->can('create', Sale::class));
        $this->assertTrue($agent->can('view', $sale));
        $this->assertTrue($agent->can('update', $sale));
        $this->assertTrue($agent->can('delete', $sale));
    }

    public function test_agent_cannot_manage_another_agents_sale(): void
    {
        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $coworker = User::factory()->for($location)->create();
        $sale = Sale::factory()->withoutAgents()->recycle($location)->create();
        $sale->agents()->attach($coworker, ['commission_percent' => 100]);

        $this->assertFalse($agent->can('view', $sale));
        $this->assertFalse($agent->can('update', $sale));
        $this->assertFalse($agent->can('delete', $sale));
    }

    public function test_agent_cannot_manage_a_sale_at_another_location(): void
    {
        $agent = User::factory()->create();
        $sale = Sale::factory()->withoutAgents()->create();
        $sale->agents()->attach($agent, ['commission_percent' => 100]);

        $this->assertFalse($agent->can('view', $sale));
        $this->assertFalse($agent->can('update', $sale));
        $this->assertFalse($agent->can('delete', $sale));
    }

    public function test_location_admin_can_manage_unassigned_sales_at_their_location(): void
    {
        $location = Location::factory()->create();
        $admin = User::factory()->admin()->for($location)->create();
        $sale = Sale::factory()->withoutAgents()->recycle($location)->create();

        $this->assertTrue($admin->can('view', $sale));
        $this->assertTrue($admin->can('update', $sale));
        $this->assertTrue($admin->can('delete', $sale));
    }

    public function test_location_admin_cannot_manage_a_sale_at_another_location(): void
    {
        $admin = User::factory()->admin()->create();
        $sale = Sale::factory()->create();

        $this->assertFalse($admin->can('view', $sale));
        $this->assertFalse($admin->can('update', $sale));
        $this->assertFalse($admin->can('delete', $sale));
    }

    public function test_super_admin_can_manage_a_sale_at_any_location(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $sale = Sale::factory()->create();

        $this->assertTrue($admin->can('viewAny', Sale::class));
        $this->assertTrue($admin->can('create', Sale::class));
        $this->assertTrue($admin->can('view', $sale));
        $this->assertTrue($admin->can('update', $sale));
        $this->assertTrue($admin->can('delete', $sale));
    }
}
