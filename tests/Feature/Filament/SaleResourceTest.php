<?php

namespace Tests\Feature\Filament;

use App\Enums\SaleType;
use App\Filament\Resources\Sales\Pages\CreateSale;
use App\Filament\Resources\Sales\Pages\EditSale;
use App\Filament\Resources\Sales\Pages\ListSales;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Sale;
use App\Models\SaleStatus;
use App\Models\SaleUser;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SaleResourceTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidCommissionPercentages(): array
    {
        return [
            'below one' => ['0.9'],
            'above six' => ['6.1'],
        ];
    }

    /**
     * @return array<string, array{0: int}>
     */
    public static function invalidAgentCommissionPercents(): array
    {
        return [
            'below one' => [0],
            'above one hundred' => [101],
        ];
    }

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

    public function test_closed_tab_shows_year_to_date_closed_sales_for_the_selected_agent(): void
    {
        $this->travelTo('2026-09-14 12:00:00');

        $agent = User::factory()->create();
        $otherAgent = User::factory()->create();
        $closedStatus = SaleStatus::factory()->create([
            'name' => 'Closed',
            'slug' => 'closed',
        ]);
        $pendingStatus = SaleStatus::factory()->create([
            'name' => 'Pending',
            'slug' => 'pending',
        ]);
        $closedSale = $this->assignAgent(
            Sale::factory()->closed()->withoutAgents()->recycle($closedStatus)->create(),
            $agent,
        );
        $pendingSale = $this->assignAgent(
            Sale::factory()->pending()->withoutAgents()->recycle($pendingStatus)->create(),
            $agent,
        );
        $lastYearClosedSale = $this->assignAgent(
            Sale::factory()->closed()->withoutAgents()->recycle($closedStatus)->create([
                'closed_at' => now()->subYear(),
            ]),
            $agent,
        );
        $otherAgentClosedSale = $this->assignAgent(
            Sale::factory()->closed()->withoutAgents()->recycle($closedStatus)->create(),
            $otherAgent,
        );

        Livewire::actingAs($agent)
            ->test(ListSales::class, [
                'activeTab' => 'closed',
                'tableFilters' => [
                    'agents' => [
                        'value' => $agent->id,
                    ],
                ],
            ])
            ->assertCanSeeTableRecords([$closedSale])
            ->assertCanNotSeeTableRecords([
                $pendingSale,
                $lastYearClosedSale,
                $otherAgentClosedSale,
            ]);
    }

    public function test_pending_tab_shows_pending_sales_for_the_selected_agent(): void
    {
        $agent = User::factory()->create();
        $otherAgent = User::factory()->create();
        $closedStatus = SaleStatus::factory()->create([
            'name' => 'Closed',
            'slug' => 'closed',
        ]);
        $pendingStatus = SaleStatus::factory()->create([
            'name' => 'Pending',
            'slug' => 'pending',
        ]);
        $pendingSale = $this->assignAgent(
            Sale::factory()->pending()->withoutAgents()->recycle($pendingStatus)->create(),
            $agent,
        );
        $closedSale = $this->assignAgent(
            Sale::factory()->closed()->withoutAgents()->recycle($closedStatus)->create(),
            $agent,
        );
        $otherAgentPendingSale = $this->assignAgent(
            Sale::factory()->pending()->withoutAgents()->recycle($pendingStatus)->create(),
            $otherAgent,
        );

        Livewire::actingAs($agent)
            ->test(ListSales::class, [
                'activeTab' => 'pending',
                'tableFilters' => [
                    'agents' => [
                        'value' => $agent->id,
                    ],
                ],
            ])
            ->assertCanSeeTableRecords([$pendingSale])
            ->assertCanNotSeeTableRecords([
                $closedSale,
                $otherAgentPendingSale,
            ]);
    }

    public function test_source_filter_shows_only_sales_from_the_selected_source(): void
    {
        $agent = User::factory()->create();
        $zillow = LeadSource::factory()->create(['name' => 'Zillow']);
        $website = LeadSource::factory()->create(['name' => 'Website']);
        $zillowSale = Sale::factory()->for(
            Lead::factory()->for($zillow, 'source'),
        )->create();
        $websiteSale = Sale::factory()->for(
            Lead::factory()->for($website, 'source'),
        )->create();

        Livewire::actingAs($agent)
            ->test(ListSales::class, [
                'tableFilters' => [
                    'source' => [
                        'value' => $zillow->id,
                    ],
                ],
            ])
            ->assertCanSeeTableRecords([$zillowSale])
            ->assertCanNotSeeTableRecords([$websiteSale]);
    }

    public function test_agent_can_create_a_sale_with_an_agent_assignment(): void
    {
        $agent = User::factory()->create();
        $lead = Lead::factory()->create();
        $status = SaleStatus::factory()->create();
        $assignedAgent = User::factory()->create();

        Livewire::actingAs($agent)
            ->test(CreateSale::class)
            ->fillForm($this->validSaleForm($lead, $status, $assignedAgent))
            ->call('create')
            ->assertHasNoFormErrors();

        $sale = Sale::query()->where('street_address', '123 Main St')->first();

        $this->assertNotNull($sale);
        $this->assertSame($lead->id, $sale->lead_id);
        $this->assertSame(SaleType::Seller, $sale->sale_type);
        $this->assertSame('3.0', $sale->commission_percentage);
        $this->assertSame('13500.00', $sale->gross_commission);
        $this->assertCount(1, $sale->agents);
        $this->assertSame(100, $sale->agents->first()?->pivot->commission_percent);
        $this->assertSame('13500.00', $sale->agents->first()?->pivot->net_commission);
    }

    public function test_closed_sale_requires_a_closed_at_date(): void
    {
        $agent = User::factory()->create();
        $lead = Lead::factory()->create();
        $status = SaleStatus::factory()->create([
            'name' => 'Closed',
            'slug' => 'closed',
        ]);
        $assignedAgent = User::factory()->create();

        Livewire::actingAs($agent)
            ->test(CreateSale::class)
            ->fillForm($this->validSaleForm($lead, $status, $assignedAgent))
            ->call('create')
            ->assertHasFormErrors(['closed_at']);

        $this->assertDatabaseMissing(Sale::class, [
            'street_address' => '123 Main St',
        ]);
    }

    public function test_closed_sale_can_be_created_with_a_closed_at_date(): void
    {
        $agent = User::factory()->create();
        $lead = Lead::factory()->create();
        $status = SaleStatus::factory()->create([
            'name' => 'Closed',
            'slug' => 'closed',
        ]);
        $assignedAgent = User::factory()->create();

        Livewire::actingAs($agent)
            ->test(CreateSale::class)
            ->fillForm($this->validSaleForm($lead, $status, $assignedAgent, [
                'closed_at' => '2026-03-15',
            ]))
            ->call('create')
            ->assertHasNoFormErrors();

        $sale = Sale::query()->where('street_address', '123 Main St')->first();

        $this->assertNotNull($sale);
        $this->assertSame('2026-03-15', $sale->closed_at?->toDateString());
    }

    public function test_updating_a_sale_to_closed_requires_a_closed_at_date(): void
    {
        $agent = User::factory()->create();
        $sale = Sale::factory()->pending()->create();
        $closedStatus = SaleStatus::factory()->create([
            'name' => 'Closed',
            'slug' => 'closed',
        ]);

        Livewire::actingAs($agent)
            ->test(EditSale::class, ['record' => $sale->getRouteKey()])
            ->fillForm([
                'sale_status_id' => $closedStatus->id,
                'closed_at' => null,
            ])
            ->call('save')
            ->assertHasFormErrors(['closed_at']);

        $sale->refresh();

        $this->assertNotSame($closedStatus->id, $sale->sale_status_id);
        $this->assertNull($sale->closed_at);
    }

    public function test_sale_requires_at_least_one_agent(): void
    {
        $agent = User::factory()->create();
        $lead = Lead::factory()->create();
        $status = SaleStatus::factory()->create();

        Livewire::actingAs($agent)
            ->test(CreateSale::class)
            ->fillForm([
                ...$this->validSaleForm($lead, $status, User::factory()->create()),
                'agentAssignments' => [],
            ])
            ->call('create')
            ->assertHasFormErrors(['agentAssignments']);

        $this->assertDatabaseMissing(Sale::class, [
            'street_address' => '123 Main St',
        ]);
    }

    public function test_sale_requires_a_sale_type(): void
    {
        $agent = User::factory()->create();
        $lead = Lead::factory()->create();
        $status = SaleStatus::factory()->create();
        $assignedAgent = User::factory()->create();

        Livewire::actingAs($agent)
            ->test(CreateSale::class)
            ->fillForm($this->validSaleForm($lead, $status, $assignedAgent, [
                'sale_type' => null,
            ]))
            ->call('create')
            ->assertHasFormErrors(['sale_type']);

        $this->assertDatabaseMissing(Sale::class, [
            'street_address' => '123 Main St',
        ]);
    }

    #[DataProvider('invalidCommissionPercentages')]
    public function test_sale_rejects_commission_percentage_outside_one_to_six(string $percentage): void
    {
        $agent = User::factory()->create();
        $lead = Lead::factory()->create();
        $status = SaleStatus::factory()->create();
        $assignedAgent = User::factory()->create();

        Livewire::actingAs($agent)
            ->test(CreateSale::class)
            ->fillForm($this->validSaleForm($lead, $status, $assignedAgent, [
                'commission_percentage' => $percentage,
            ]))
            ->call('create')
            ->assertHasFormErrors(['commission_percentage']);

        $this->assertDatabaseMissing(Sale::class, [
            'street_address' => '123 Main St',
        ]);
    }

    #[DataProvider('invalidAgentCommissionPercents')]
    public function test_sale_rejects_agent_commission_percent_outside_one_to_one_hundred(int $percent): void
    {
        $agent = User::factory()->create();
        $lead = Lead::factory()->create();
        $status = SaleStatus::factory()->create();
        $assignedAgent = User::factory()->create();

        Livewire::actingAs($agent)
            ->test(CreateSale::class)
            ->fillForm($this->validSaleForm($lead, $status, $assignedAgent, [
                'agentAssignments' => [
                    [
                        'user_id' => $assignedAgent->id,
                        'commission_percent' => $percent,
                    ],
                ],
            ]))
            ->call('create')
            ->assertHasFormErrors(['agentAssignments.0.commission_percent']);

        $this->assertDatabaseMissing(Sale::class, [
            'street_address' => '123 Main St',
        ]);
    }

    public function test_sale_rejects_agent_splits_that_do_not_total_one_hundred(): void
    {
        $agent = User::factory()->create();
        $lead = Lead::factory()->create();
        $status = SaleStatus::factory()->create();
        $listingAgent = User::factory()->create();
        $buyersAgent = User::factory()->create();

        Livewire::actingAs($agent)
            ->test(CreateSale::class)
            ->fillForm($this->validSaleForm($lead, $status, $listingAgent, [
                'agentAssignments' => [
                    [
                        'user_id' => $listingAgent->id,
                        'commission_percent' => 90,
                    ],
                    [
                        'user_id' => $buyersAgent->id,
                        'commission_percent' => 20,
                    ],
                ],
            ]))
            ->call('create')
            ->assertHasFormErrors(['agentAssignments']);

        $this->assertDatabaseMissing(Sale::class, [
            'street_address' => '123 Main St',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validSaleForm(Lead $lead, SaleStatus $status, User $assignedAgent, array $overrides = []): array
    {
        return [
            'lead_id' => $lead->id,
            'sale_status_id' => $status->id,
            'sale_type' => SaleType::Seller,
            'street_address' => '123 Main St',
            'city' => 'Austin',
            'state' => 'TX',
            'postal_code' => '78701',
            'price' => '450000.00',
            'commission_percentage' => '3.0',
            'agentAssignments' => [
                [
                    'user_id' => $assignedAgent->id,
                    'commission_percent' => 100,
                ],
            ],
            ...$overrides,
        ];
    }

    private function assignAgent(Sale $sale, User $agent): Sale
    {
        $sale->agents()->attach($agent, [
            'commission_percent' => 100,
            'net_commission' => SaleUser::netCommissionFor($sale->gross_commission, 100),
        ]);

        return $sale;
    }
}
