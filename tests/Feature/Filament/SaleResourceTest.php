<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Sales\Pages\CreateSale;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\Lead;
use App\Models\Sale;
use App\Models\SaleStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SaleResourceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_from_sales(): void
    {
        $this->get(SaleResource::getUrl(isAbsolute: false))->assertRedirect();
    }

    public function test_agent_can_view_sales(): void
    {
        $agent = User::factory()->create();

        $this->actingAs($agent)
            ->get(SaleResource::getUrl(isAbsolute: false))
            ->assertOk();
    }

    public function test_agent_can_create_a_sale_with_an_agent_assignment(): void
    {
        $agent = User::factory()->create();
        $lead = Lead::factory()->create();
        $status = SaleStatus::factory()->create();
        $assignedAgent = User::factory()->create();

        Livewire::actingAs($agent)
            ->test(CreateSale::class)
            ->fillForm([
                'lead_id' => $lead->id,
                'sale_status_id' => $status->id,
                'street_address' => '123 Main St',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '78701',
                'price' => '450000.00',
                'agentAssignments' => [
                    [
                        'user_id' => $assignedAgent->id,
                        'commission_percent' => '3.00',
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $sale = Sale::query()->where('street_address', '123 Main St')->first();

        $this->assertNotNull($sale);
        $this->assertSame($lead->id, $sale->lead_id);
        $this->assertCount(1, $sale->agents);
        $this->assertSame('3.00', $sale->agents->first()?->pivot->commission_percent);
    }

    public function test_sale_requires_at_least_one_agent(): void
    {
        $agent = User::factory()->create();
        $lead = Lead::factory()->create();
        $status = SaleStatus::factory()->create();

        Livewire::actingAs($agent)
            ->test(CreateSale::class)
            ->fillForm([
                'lead_id' => $lead->id,
                'sale_status_id' => $status->id,
                'street_address' => '123 Main St',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '78701',
                'price' => '450000.00',
                'agentAssignments' => [],
            ])
            ->call('create')
            ->assertHasFormErrors(['agentAssignments']);
    }
}
