<?php

namespace Tests\Feature;

use App\Models\LeadSource;
use App\Models\LeadStatus;
use App\Models\SaleStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LeadStatusPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: class-string}>
     */
    public static function lookupModels(): array
    {
        return [
            'lead status' => [LeadStatus::class],
            'sale status' => [SaleStatus::class],
            'lead source' => [LeadSource::class],
        ];
    }

    #[DataProvider('lookupModels')]
    public function test_admin_can_manage_lookup_records(string $model): void
    {
        $admin = User::factory()->admin()->create();
        $record = $model::factory()->create();

        $this->assertTrue($admin->can('viewAny', $model));
        $this->assertTrue($admin->can('create', $model));
        $this->assertTrue($admin->can('update', $record));
        $this->assertTrue($admin->can('delete', $record));
        $this->assertTrue($admin->can('deleteAny', $model));
    }

    #[DataProvider('lookupModels')]
    public function test_agent_cannot_manage_lookup_records(string $model): void
    {
        $agent = User::factory()->create();
        $record = $model::factory()->create();

        $this->assertFalse($agent->can('viewAny', $model));
        $this->assertFalse($agent->can('create', $model));
        $this->assertFalse($agent->can('update', $record));
        $this->assertFalse($agent->can('delete', $record));
        $this->assertFalse($agent->can('deleteAny', $model));
    }
}
