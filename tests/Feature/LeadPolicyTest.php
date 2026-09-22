<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Location;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LeadPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: class-string}>
     */
    public static function locationOwnedModels(): array
    {
        return [
            'lead' => [Lead::class],
            'sale' => [Sale::class],
        ];
    }

    #[DataProvider('locationOwnedModels')]
    public function test_members_can_manage_records_at_their_location(string $model): void
    {
        $location = Location::factory()->create();
        $user = User::factory()->for($location)->create();
        $record = $model::factory()->recycle($location)->create();

        $this->assertTrue($user->can('viewAny', $model));
        $this->assertTrue($user->can('create', $model));
        $this->assertTrue($user->can('view', $record));
        $this->assertTrue($user->can('update', $record));
        $this->assertTrue($user->can('delete', $record));
        $this->assertTrue($user->can('deleteAny', $model));
    }

    #[DataProvider('locationOwnedModels')]
    public function test_members_cannot_manage_records_at_another_location(string $model): void
    {
        $user = User::factory()->create();
        $record = $model::factory()->create();

        $this->assertFalse($user->can('view', $record));
        $this->assertFalse($user->can('update', $record));
        $this->assertFalse($user->can('delete', $record));
    }

    #[DataProvider('locationOwnedModels')]
    public function test_super_admin_can_manage_records_at_any_location(string $model): void
    {
        $admin = User::factory()->superAdmin()->create();
        $record = $model::factory()->create();

        $this->assertTrue($admin->can('viewAny', $model));
        $this->assertTrue($admin->can('create', $model));
        $this->assertTrue($admin->can('view', $record));
        $this->assertTrue($admin->can('update', $record));
        $this->assertTrue($admin->can('delete', $record));
    }
}
