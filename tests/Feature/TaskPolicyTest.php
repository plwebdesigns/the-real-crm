<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TaskPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_members_can_manage_tasks_for_leads_at_their_location(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()
            ->for(Lead::factory()->recycle($user->location))
            ->create();

        $this->assertTrue($user->can('viewAny', Task::class));
        $this->assertTrue($user->can('create', Task::class));
        $this->assertTrue($user->can('view', $task));
        $this->assertTrue($user->can('update', $task));
        $this->assertTrue($user->can('delete', $task));
    }

    public function test_members_cannot_manage_tasks_for_leads_at_another_location(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create();

        $this->assertFalse($user->can('view', $task));
        $this->assertFalse($user->can('update', $task));
        $this->assertFalse($user->can('delete', $task));
    }

    public function test_super_admin_can_manage_tasks_at_any_location(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $task = Task::factory()->create();

        $this->assertTrue($admin->can('viewAny', Task::class));
        $this->assertTrue($admin->can('create', Task::class));
        $this->assertTrue($admin->can('view', $task));
        $this->assertTrue($admin->can('update', $task));
        $this->assertTrue($admin->can('delete', $task));
    }
}
