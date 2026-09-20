<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\Analytics;
use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Resources\Sales\SaleResource;
use App\Filament\Widgets\AgentPerformanceTable;
use App\Filament\Widgets\AssignedLeadsTable;
use App\Filament\Widgets\FirmLeadsStatsOverview;
use App\Filament\Widgets\FirmSalesStatsOverview;
use App\Filament\Widgets\LeadSourcePerformanceTable;
use App\Filament\Widgets\LeadsStatsOverview;
use App\Filament\Widgets\SalesStatsOverview;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Sale;
use App\Models\SaleStatus;
use App\Models\SaleUser;
use App\Models\User;
use Filament\Pages\Dashboard;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_from_analytics(): void
    {
        $this->get(Analytics::getUrl(isAbsolute: false))->assertRedirect();
    }

    public function test_agent_cannot_view_analytics(): void
    {
        $agent = User::factory()->create();

        $this->actingAs($agent)
            ->get(Analytics::getUrl(isAbsolute: false))
            ->assertForbidden();
    }

    public function test_admin_can_view_analytics(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(Analytics::getUrl(isAbsolute: false))
            ->assertOk()
            ->assertSeeLivewire(FirmSalesStatsOverview::class)
            ->assertSeeLivewire(FirmLeadsStatsOverview::class)
            ->assertSeeLivewire(AgentPerformanceTable::class)
            ->assertSeeLivewire(LeadSourcePerformanceTable::class)
            ->assertDontSeeLivewire(SalesStatsOverview::class)
            ->assertDontSeeLivewire(LeadsStatsOverview::class)
            ->assertDontSeeLivewire(AssignedLeadsTable::class);
    }

    public function test_personal_dashboard_does_not_include_firm_analytics_widgets(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(Dashboard::getUrl(isAbsolute: false))
            ->assertOk()
            ->assertSeeLivewire(SalesStatsOverview::class)
            ->assertDontSeeLivewire(FirmSalesStatsOverview::class)
            ->assertDontSeeLivewire(FirmLeadsStatsOverview::class)
            ->assertDontSeeLivewire(AgentPerformanceTable::class)
            ->assertDontSeeLivewire(LeadSourcePerformanceTable::class);
    }

    public function test_admin_sees_year_to_date_firm_closed_and_pending_sales_stats(): void
    {
        $this->travelTo('2026-09-14 12:00:00');

        $admin = User::factory()->admin()->create();
        $firstAgent = User::factory()->create();
        $secondAgent = User::factory()->create();
        $closedStatus = $this->closedStatus();
        $pendingStatus = $this->pendingStatus();
        $this->assignAgent(
            Sale::factory()->closed()->withoutAgents()->recycle($closedStatus)->create([
                'price' => '450000.00',
                'commission_percentage' => '3.0',
            ]),
            $firstAgent,
        );
        $this->assignAgent(
            Sale::factory()->closed()->withoutAgents()->recycle($closedStatus)->create([
                'price' => '200000.00',
                'commission_percentage' => '3.0',
            ]),
            $secondAgent,
        );
        $this->assignAgent(
            Sale::factory()->pending()->withoutAgents()->recycle($pendingStatus)->create([
                'price' => '300000.00',
                'commission_percentage' => '3.0',
            ]),
            $firstAgent,
        );

        Livewire::actingAs($admin)
            ->test(FirmSalesStatsOverview::class)
            ->assertSee('Closed sales')
            ->assertSee('2')
            ->assertSee('$650,000.00')
            ->assertSee('Gross commission')
            ->assertSee('$19,500.00')
            ->assertSee('Pending sales')
            ->assertSee('1')
            ->assertSee('Year to date')
            ->assertSee('Open pipeline');
    }

    public function test_firm_closed_sales_stats_count_a_split_sale_once(): void
    {
        $this->travelTo('2026-09-14 12:00:00');

        $admin = User::factory()->admin()->create();
        $firstAgent = User::factory()->create();
        $secondAgent = User::factory()->create();
        $sale = Sale::factory()->closed()->withoutAgents()->recycle($this->closedStatus())->create([
            'price' => '450000.00',
            'commission_percentage' => '3.0',
        ]);
        $sale->agents()->attach([
            $firstAgent->id => [
                'commission_percent' => 50,
                'net_commission' => SaleUser::netCommissionFor(
                    Sale::remainingCommissionFor($sale->gross_commission, $sale->brokerage_fee),
                    50,
                ),
            ],
            $secondAgent->id => [
                'commission_percent' => 50,
                'net_commission' => SaleUser::netCommissionFor(
                    Sale::remainingCommissionFor($sale->gross_commission, $sale->brokerage_fee),
                    50,
                ),
            ],
        ]);

        Livewire::actingAs($admin)
            ->test(FirmSalesStatsOverview::class)
            ->assertSee('Closed sales')
            ->assertSee('1')
            ->assertSee('$450,000.00')
            ->assertSee('$13,500.00');
    }

    public function test_firm_sales_stats_do_not_include_last_years_closed_sale(): void
    {
        $this->travelTo('2026-09-14 12:00:00');

        $admin = User::factory()->admin()->create();
        $agent = User::factory()->create();
        $closedStatus = $this->closedStatus();
        $this->assignAgent(
            Sale::factory()->closed()->withoutAgents()->recycle($closedStatus)->create([
                'price' => '450000.00',
                'commission_percentage' => '3.0',
            ]),
            $agent,
        );
        $this->assignAgent(
            Sale::factory()->closed()->withoutAgents()->recycle($closedStatus)->create([
                'price' => '800000.00',
                'commission_percentage' => '3.0',
                'closed_at' => now()->subYear(),
            ]),
            $agent,
        );

        Livewire::actingAs($admin)
            ->test(FirmSalesStatsOverview::class)
            ->assertSee('$450,000.00')
            ->assertSee('$13,500.00')
            ->assertDontSee('$800,000.00')
            ->assertDontSee('$24,000.00');
    }

    public function test_firm_sales_stats_link_to_the_matching_sales_list_without_an_agent_filter(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(FirmSalesStatsOverview::class)
            ->assertSeeHtml(e($this->salesIndexUrl('closed')))
            ->assertSeeHtml(e($this->salesIndexUrl('pending')))
            ->assertDontSeeHtml('filters');
    }

    public function test_admin_sees_all_working_lost_and_closed_lead_stats(): void
    {
        $admin = User::factory()->admin()->create();
        $agent = User::factory()->create();
        $otherAgent = User::factory()->create();
        Lead::factory()->asNew()->create();
        $this->assignLead(Lead::factory()->contacted()->create(), $agent);
        $this->assignLead(Lead::factory()->lost()->create(), $otherAgent);
        $qualified = $this->assignLead(Lead::factory()->qualified()->create(), $otherAgent);
        Sale::factory()->closed()->for($qualified)->create();

        Livewire::actingAs($admin)
            ->test(FirmLeadsStatsOverview::class)
            ->assertSeeInOrder([
                'Total leads',
                '4',
                'Working leads',
                '2',
                'Lost leads',
                '1',
                'Percent of leads closed',
                '25%',
            ]);
    }

    public function test_firm_lead_stats_link_to_the_matching_leads_list_without_an_agent_filter(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(FirmLeadsStatsOverview::class)
            ->assertSeeHtml(e($this->leadsIndexUrl('all')))
            ->assertSeeHtml(e($this->leadsIndexUrl('working')))
            ->assertSeeHtml(e($this->leadsIndexUrl('lost')))
            ->assertSeeHtml(e($this->leadsIndexUrl('closed')))
            ->assertDontSeeHtml('filters');
    }

    public function test_agent_performance_table_shows_each_agents_own_numbers(): void
    {
        $this->travelTo('2026-09-14 12:00:00');

        $admin = User::factory()->admin()->create();
        $firstAgent = User::factory()->create(['name' => 'Ada Agent']);
        $secondAgent = User::factory()->create(['name' => 'Jordan Agent']);
        $closedStatus = $this->closedStatus();
        $pendingStatus = $this->pendingStatus();
        $this->assignAgent(
            Sale::factory()->closed()->withoutAgents()->recycle($closedStatus)->create([
                'price' => '450000.00',
                'commission_percentage' => '3.0',
            ]),
            $firstAgent,
        );
        $this->assignAgent(
            Sale::factory()->pending()->withoutAgents()->recycle($pendingStatus)->create([
                'price' => '300000.00',
                'commission_percentage' => '3.0',
            ]),
            $firstAgent,
        );
        $this->assignAgent(
            Sale::factory()->closed()->withoutAgents()->recycle($closedStatus)->create([
                'price' => '200000.00',
                'commission_percentage' => '3.0',
            ]),
            $secondAgent,
        );
        $this->assignLead(Lead::factory()->contacted()->create(), $firstAgent);
        $this->assignLead(Lead::factory()->lost()->create(), $firstAgent);
        $qualified = $this->assignLead(Lead::factory()->qualified()->create(), $secondAgent);
        Sale::factory()->closed()->for($qualified)->recycle($closedStatus)->create();

        Livewire::actingAs($admin)
            ->test(AgentPerformanceTable::class)
            ->assertCanSeeTableRecords([$firstAgent, $secondAgent, $admin])
            ->assertTableColumnStateSet('closed_sales_count', 1, $firstAgent)
            ->assertTableColumnStateSet('closed_volume', '450000.00', $firstAgent)
            ->assertTableColumnStateSet('brokerage_fee', '250.00', $firstAgent)
            ->assertTableColumnStateSet('net_commission', '13250.00', $firstAgent)
            ->assertTableColumnStateSet('pending_sales_count', 1, $firstAgent)
            ->assertTableColumnStateSet('assigned_leads_count', 2, $firstAgent)
            ->assertTableColumnStateSet('working_leads_count', 1, $firstAgent)
            ->assertTableColumnStateSet('closed_percent', '0%', $firstAgent)
            ->assertTableColumnStateSet('closed_sales_count', 1, $secondAgent)
            ->assertTableColumnStateSet('closed_volume', '200000.00', $secondAgent)
            ->assertTableColumnStateSet('brokerage_fee', '250.00', $secondAgent)
            ->assertTableColumnStateSet('net_commission', '5750.00', $secondAgent)
            ->assertTableColumnStateSet('pending_sales_count', 0, $secondAgent)
            ->assertTableColumnStateSet('assigned_leads_count', 1, $secondAgent)
            ->assertTableColumnStateSet('working_leads_count', 1, $secondAgent)
            ->assertTableColumnStateSet('closed_percent', '100%', $secondAgent);
    }

    public function test_agent_performance_table_counts_split_sale_volume_for_each_agent(): void
    {
        $this->travelTo('2026-09-14 12:00:00');

        $admin = User::factory()->admin()->create();
        $firstAgent = User::factory()->create(['name' => 'Ada Agent']);
        $secondAgent = User::factory()->create(['name' => 'Jordan Agent']);
        $sale = Sale::factory()->closed()->withoutAgents()->recycle($this->closedStatus())->create([
            'price' => '450000.00',
            'commission_percentage' => '3.0',
        ]);
        $sale->agents()->attach([
            $firstAgent->id => [
                'commission_percent' => 50,
                'net_commission' => SaleUser::netCommissionFor(
                    Sale::remainingCommissionFor($sale->gross_commission, $sale->brokerage_fee),
                    50,
                ),
            ],
            $secondAgent->id => [
                'commission_percent' => 50,
                'net_commission' => SaleUser::netCommissionFor(
                    Sale::remainingCommissionFor($sale->gross_commission, $sale->brokerage_fee),
                    50,
                ),
            ],
        ]);

        Livewire::actingAs($admin)
            ->test(AgentPerformanceTable::class)
            ->assertTableColumnStateSet('closed_volume', '450000.00', $firstAgent)
            ->assertTableColumnStateSet('closed_volume', '450000.00', $secondAgent)
            ->assertTableColumnStateSet('brokerage_fee', '250.00', $firstAgent)
            ->assertTableColumnStateSet('brokerage_fee', '250.00', $secondAgent)
            ->assertTableColumnStateSet('net_commission', '6625.00', $firstAgent)
            ->assertTableColumnStateSet('net_commission', '6625.00', $secondAgent);
    }

    public function test_agent_name_links_to_the_closed_sales_list_for_that_agent(): void
    {
        $admin = User::factory()->admin()->create();
        $agent = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(AgentPerformanceTable::class)
            ->assertSeeHtml(e($this->agentSalesIndexUrl($agent)));
    }

    public function test_lead_source_performance_table_shows_each_sources_own_numbers(): void
    {
        $this->travelTo('2026-09-14 12:00:00');

        $admin = User::factory()->admin()->create();
        $zillow = LeadSource::factory()->create(['name' => 'Zillow']);
        $website = LeadSource::factory()->create(['name' => 'Website']);
        $openHouse = LeadSource::factory()->create(['name' => 'Open House']);
        $closedStatus = $this->closedStatus();
        $pendingStatus = $this->pendingStatus();
        $zillowClosedLead = Lead::factory()->qualified()->for($zillow, 'source')->create();
        $zillowPendingLead = Lead::factory()->contacted()->for($zillow, 'source')->create();
        Sale::factory()->closed()->withoutAgents()->for($zillowClosedLead)->recycle($closedStatus)->create([
            'price' => '450000.00',
            'commission_percentage' => '3.0',
        ]);
        Sale::factory()->pending()->withoutAgents()->for($zillowPendingLead)->recycle($pendingStatus)->create([
            'price' => '300000.00',
            'commission_percentage' => '3.0',
        ]);
        $websiteClosedLead = Lead::factory()->qualified()->for($website, 'source')->create();
        Sale::factory()->closed()->withoutAgents()->for($websiteClosedLead)->recycle($closedStatus)->create([
            'price' => '200000.00',
            'commission_percentage' => '3.0',
        ]);

        Livewire::actingAs($admin)
            ->test(LeadSourcePerformanceTable::class)
            ->assertCanSeeTableRecords([$zillow, $website, $openHouse])
            ->assertTableColumnStateSet('closed_sales_count', 1, $zillow)
            ->assertTableColumnStateSet('closed_volume', '450000.00', $zillow)
            ->assertTableColumnStateSet('gross_commission', '13500.00', $zillow)
            ->assertTableColumnStateSet('pending_sales_count', 1, $zillow)
            ->assertTableColumnStateSet('leads_count', 2, $zillow)
            ->assertTableColumnStateSet('closed_percent', '50%', $zillow)
            ->assertTableColumnStateSet('closed_sales_count', 1, $website)
            ->assertTableColumnStateSet('closed_volume', '200000.00', $website)
            ->assertTableColumnStateSet('gross_commission', '6000.00', $website)
            ->assertTableColumnStateSet('pending_sales_count', 0, $website)
            ->assertTableColumnStateSet('leads_count', 1, $website)
            ->assertTableColumnStateSet('closed_percent', '100%', $website)
            ->assertTableColumnStateSet('closed_sales_count', 0, $openHouse)
            ->assertTableColumnStateSet('closed_volume', '0.00', $openHouse)
            ->assertTableColumnStateSet('gross_commission', '0.00', $openHouse)
            ->assertTableColumnStateSet('pending_sales_count', 0, $openHouse)
            ->assertTableColumnStateSet('leads_count', 0, $openHouse)
            ->assertTableColumnStateSet('closed_percent', '0%', $openHouse);
    }

    public function test_lead_source_performance_table_does_not_include_last_years_closed_sale(): void
    {
        $this->travelTo('2026-09-14 12:00:00');

        $admin = User::factory()->admin()->create();
        $zillow = LeadSource::factory()->create(['name' => 'Zillow']);
        $closedStatus = $this->closedStatus();
        $thisYearLead = Lead::factory()->qualified()->for($zillow, 'source')->create();
        $lastYearLead = Lead::factory()->qualified()->for($zillow, 'source')->create();
        Sale::factory()->closed()->withoutAgents()->for($thisYearLead)->recycle($closedStatus)->create([
            'price' => '450000.00',
            'commission_percentage' => '3.0',
        ]);
        Sale::factory()->closed()->withoutAgents()->for($lastYearLead)->recycle($closedStatus)->create([
            'price' => '800000.00',
            'commission_percentage' => '3.0',
            'closed_at' => now()->subYear(),
        ]);

        Livewire::actingAs($admin)
            ->test(LeadSourcePerformanceTable::class)
            ->assertTableColumnStateSet('closed_sales_count', 1, $zillow)
            ->assertTableColumnStateSet('closed_volume', '450000.00', $zillow)
            ->assertTableColumnStateSet('gross_commission', '13500.00', $zillow);
    }

    public function test_lead_source_performance_table_counts_a_split_sale_once(): void
    {
        $this->travelTo('2026-09-14 12:00:00');

        $admin = User::factory()->admin()->create();
        $zillow = LeadSource::factory()->create(['name' => 'Zillow']);
        $firstAgent = User::factory()->create();
        $secondAgent = User::factory()->create();
        $lead = Lead::factory()->qualified()->for($zillow, 'source')->create();
        $sale = Sale::factory()->closed()->withoutAgents()->for($lead)->recycle($this->closedStatus())->create([
            'price' => '450000.00',
            'commission_percentage' => '3.0',
        ]);
        $sale->agents()->attach([
            $firstAgent->id => [
                'commission_percent' => 50,
                'net_commission' => SaleUser::netCommissionFor(
                    Sale::remainingCommissionFor($sale->gross_commission, $sale->brokerage_fee),
                    50,
                ),
            ],
            $secondAgent->id => [
                'commission_percent' => 50,
                'net_commission' => SaleUser::netCommissionFor(
                    Sale::remainingCommissionFor($sale->gross_commission, $sale->brokerage_fee),
                    50,
                ),
            ],
        ]);

        Livewire::actingAs($admin)
            ->test(LeadSourcePerformanceTable::class)
            ->assertTableColumnStateSet('closed_sales_count', 1, $zillow)
            ->assertTableColumnStateSet('closed_volume', '450000.00', $zillow)
            ->assertTableColumnStateSet('gross_commission', '13500.00', $zillow);
    }

    public function test_lead_source_name_links_to_the_closed_sales_list_for_that_source(): void
    {
        $admin = User::factory()->admin()->create();
        $source = LeadSource::factory()->create();

        Livewire::actingAs($admin)
            ->test(LeadSourcePerformanceTable::class)
            ->assertSeeHtml(e($this->sourceSalesIndexUrl($source)));
    }

    private function assignLead(Lead $lead, User $agent): Lead
    {
        $lead->agents()->attach($agent);

        return $lead;
    }

    private function assignAgent(Sale $sale, User $agent): Sale
    {
        $sale->agents()->attach($agent, [
            'commission_percent' => 100,
            'net_commission' => SaleUser::netCommissionFor(
                Sale::remainingCommissionFor($sale->gross_commission, $sale->brokerage_fee),
                100,
            ),
        ]);

        return $sale;
    }

    private function salesIndexUrl(string $tab): string
    {
        return SaleResource::getUrl('index', [
            'tab' => $tab,
        ], isAbsolute: false);
    }

    private function agentSalesIndexUrl(User $agent): string
    {
        return SaleResource::getUrl('index', [
            'tab' => 'closed',
            'filters' => [
                'agents' => [
                    'value' => $agent->id,
                ],
            ],
        ], isAbsolute: false);
    }

    private function sourceSalesIndexUrl(LeadSource $source): string
    {
        return SaleResource::getUrl('index', [
            'tab' => 'closed',
            'filters' => [
                'source' => [
                    'value' => $source->id,
                ],
            ],
        ], isAbsolute: false);
    }

    private function leadsIndexUrl(string $tab): string
    {
        return LeadResource::getUrl('index', [
            'tab' => $tab,
        ], isAbsolute: false);
    }

    private function closedStatus(): SaleStatus
    {
        return SaleStatus::factory()->create([
            'name' => 'Closed',
            'slug' => 'closed',
        ]);
    }

    private function pendingStatus(): SaleStatus
    {
        return SaleStatus::factory()->create([
            'name' => 'Pending',
            'slug' => 'pending',
        ]);
    }
}
