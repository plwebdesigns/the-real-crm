<?php

namespace Tests\Feature\Filament;

use App\Filament\Widgets\AssignedLeadsTable;
use App\Filament\Widgets\SalesStatsOverview;
use App\Models\Lead;
use App\Models\Sale;
use App\Models\SaleStatus;
use App\Models\SaleUser;
use App\Models\User;
use Filament\Pages\Dashboard;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_from_the_dashboard(): void
    {
        $this->get(Dashboard::getUrl(isAbsolute: false))->assertRedirect();
    }

    public function test_agent_can_view_the_dashboard(): void
    {
        $agent = User::factory()->create();

        $this->actingAs($agent)
            ->get(Dashboard::getUrl(isAbsolute: false))
            ->assertOk();
    }

    public function test_agent_sees_year_to_date_closed_and_pending_sales_stats(): void
    {
        $this->travelTo('2026-09-14 12:00:00');

        $agent = User::factory()->create();
        $closedStatus = $this->closedStatus();
        $pendingStatus = $this->pendingStatus();
        $this->assignAgent(
            Sale::factory()->closed()->withoutAgents()->recycle($closedStatus)->create([
                'price' => '450000.00',
                'commission_percentage' => '3.0',
            ]),
            $agent,
        );
        $this->assignAgent(
            Sale::factory()->closed()->withoutAgents()->recycle($closedStatus)->create([
                'price' => '200000.00',
                'commission_percentage' => '3.0',
            ]),
            $agent,
        );
        $this->assignAgent(
            Sale::factory()->pending()->withoutAgents()->recycle($pendingStatus)->create([
                'price' => '300000.00',
                'commission_percentage' => '3.0',
            ]),
            $agent,
        );

        Livewire::actingAs($agent)
            ->test(SalesStatsOverview::class)
            ->assertSee('Closed sales')
            ->assertSee('2')
            ->assertSee('$650,000.00')
            ->assertSee('$19,500.00')
            ->assertSee('Pending sales')
            ->assertSee('1')
            ->assertSee('Year to date')
            ->assertSee('Open pipeline');
    }

    public function test_sales_stats_do_not_include_another_agents_closed_sale(): void
    {
        $this->travelTo('2026-09-14 12:00:00');

        $agent = User::factory()->create();
        $otherAgent = User::factory()->create();
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
            ]),
            $otherAgent,
        );

        Livewire::actingAs($agent)
            ->test(SalesStatsOverview::class)
            ->assertSee('$450,000.00')
            ->assertSee('$13,500.00')
            ->assertDontSee('$800,000.00')
            ->assertDontSee('$24,000.00');
    }

    public function test_sales_stats_do_not_include_last_years_closed_sale(): void
    {
        $this->travelTo('2026-09-14 12:00:00');

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

        Livewire::actingAs($agent)
            ->test(SalesStatsOverview::class)
            ->assertSee('$450,000.00')
            ->assertSee('$13,500.00')
            ->assertDontSee('$800,000.00')
            ->assertDontSee('$24,000.00');
    }

    public function test_assigned_leads_table_shows_only_leads_attached_to_the_current_user(): void
    {
        $agent = User::factory()->create();
        $otherAgent = User::factory()->create();
        $assignedLead = Lead::factory()->create([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
        ]);
        $otherLead = Lead::factory()->create([
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
        ]);
        $assignedLead->agents()->attach($agent);
        $otherLead->agents()->attach($otherAgent);

        Livewire::actingAs($agent)
            ->test(AssignedLeadsTable::class)
            ->assertCanSeeTableRecords([$assignedLead])
            ->assertCanNotSeeTableRecords([$otherLead]);
    }

    private function assignAgent(Sale $sale, User $agent): Sale
    {
        $sale->agents()->attach($agent, [
            'commission_percent' => 100,
            'net_commission' => SaleUser::netCommissionFor($sale->gross_commission, 100),
        ]);

        return $sale;
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
