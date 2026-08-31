<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\LeadSources\LeadSourceResource;
use App\Filament\Resources\LeadStatuses\LeadStatusResource;
use App\Filament\Resources\LeadStatuses\Pages\ManageLeadStatuses;
use App\Filament\Resources\SaleStatuses\SaleStatusResource;
use App\Models\LeadStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LeadStatusResourceTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: class-string}>
     */
    public static function lookupResources(): array
    {
        return [
            'lead statuses' => [LeadStatusResource::class],
            'sale statuses' => [SaleStatusResource::class],
            'lead sources' => [LeadSourceResource::class],
        ];
    }

    #[DataProvider('lookupResources')]
    public function test_guest_is_redirected_from_lookup_resource(string $resource): void
    {
        $this->get($resource::getUrl(isAbsolute: false))->assertRedirect();
    }

    #[DataProvider('lookupResources')]
    public function test_admin_can_view_lookup_resource(string $resource): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get($resource::getUrl(isAbsolute: false))
            ->assertOk();
    }

    #[DataProvider('lookupResources')]
    public function test_agent_cannot_view_lookup_resource(string $resource): void
    {
        $agent = User::factory()->create();

        $this->actingAs($agent)
            ->get($resource::getUrl(isAbsolute: false))
            ->assertForbidden();
    }

    public function test_admin_can_create_a_lead_status(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(ManageLeadStatuses::class)
            ->callAction('create', [
                'name' => 'Warm',
                'slug' => 'warm',
            ]);

        $this->assertDatabaseHas(LeadStatus::class, [
            'name' => 'Warm',
            'slug' => 'warm',
        ]);
    }
}
