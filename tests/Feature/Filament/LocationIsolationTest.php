<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Resources\Leads\Pages\ListLeads;
use App\Filament\Resources\Sales\Pages\ListSales;
use App\Filament\Resources\Sales\SaleResource;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\FirmSalesStatsOverview;
use App\Models\Lead;
use App\Models\Location;
use App\Models\Sale;
use App\Models\SaleStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LocationIsolationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_location_admin_cannot_open_another_locations_agent(): void
    {
        $miami = Location::factory()->create();
        $boston = Location::factory()->create();
        $admin = User::factory()->admin()->for($miami)->create();
        $otherAgent = User::factory()->for($boston)->create();

        $this->actingAs($admin)
            ->get(UserResource::getUrl('edit', ['record' => $otherAgent], isAbsolute: false))
            ->assertNotFound();
    }

    public function test_agent_cannot_open_another_locations_lead(): void
    {
        $miami = Location::factory()->create();
        $boston = Location::factory()->create();
        $agent = User::factory()->for($miami)->create();
        $otherLead = Lead::factory()->recycle($boston)->create();

        $this->actingAs($agent)
            ->get(LeadResource::getUrl('edit', ['record' => $otherLead], isAbsolute: false))
            ->assertNotFound();
    }

    public function test_agent_cannot_open_another_locations_sale(): void
    {
        $miami = Location::factory()->create();
        $boston = Location::factory()->create();
        $agent = User::factory()->for($miami)->create();
        $otherSale = Sale::factory()->recycle($boston)->create();

        $this->actingAs($agent)
            ->get(SaleResource::getUrl('edit', ['record' => $otherSale], isAbsolute: false))
            ->assertNotFound();
    }

    public function test_agent_does_not_see_another_locations_leads_in_the_list(): void
    {
        $miami = Location::factory()->create();
        $boston = Location::factory()->create();
        $agent = User::factory()->for($miami)->create();
        $localLead = Lead::factory()->recycle($miami)->create();
        $otherLead = Lead::factory()->recycle($boston)->create();

        Livewire::actingAs($agent)
            ->test(ListLeads::class)
            ->assertCanSeeTableRecords([$localLead])
            ->assertCanNotSeeTableRecords([$otherLead]);
    }

    public function test_location_admin_sales_stats_exclude_other_locations(): void
    {
        $this->travelTo('2026-09-14 12:00:00');

        $miami = Location::factory()->create();
        $boston = Location::factory()->create();
        $admin = User::factory()->admin()->for($miami)->create();
        $closedStatus = SaleStatus::factory()->create([
            'name' => 'Closed',
            'slug' => 'closed',
        ]);

        Sale::factory()->closed()->withoutAgents()->recycle([$closedStatus, $miami])->create([
            'price' => '450000.00',
            'commission_percentage' => '3.0',
        ]);
        Sale::factory()->closed()->withoutAgents()->recycle([$closedStatus, $boston])->create([
            'price' => '200000.00',
            'commission_percentage' => '3.0',
        ]);

        Livewire::actingAs($admin)
            ->test(FirmSalesStatsOverview::class)
            ->assertSee('Closed sales')
            ->assertSee('1')
            ->assertSee('$450,000.00')
            ->assertDontSee('$200,000.00')
            ->assertDontSee('$650,000.00');
    }

    public function test_super_admin_sales_stats_include_every_location(): void
    {
        $this->travelTo('2026-09-14 12:00:00');

        $miami = Location::factory()->create();
        $boston = Location::factory()->create();
        $admin = User::factory()->superAdmin()->create();
        $closedStatus = SaleStatus::factory()->create([
            'name' => 'Closed',
            'slug' => 'closed',
        ]);

        Sale::factory()->closed()->withoutAgents()->recycle([$closedStatus, $miami])->create([
            'price' => '450000.00',
            'commission_percentage' => '3.0',
        ]);
        Sale::factory()->closed()->withoutAgents()->recycle([$closedStatus, $boston])->create([
            'price' => '200000.00',
            'commission_percentage' => '3.0',
        ]);

        Livewire::actingAs($admin)
            ->test(FirmSalesStatsOverview::class)
            ->assertSee('Closed sales')
            ->assertSee('2')
            ->assertSee('$650,000.00');
    }

    public function test_location_admin_sales_stats_ignore_another_locations_page_filter(): void
    {
        $this->travelTo('2026-09-14 12:00:00');

        $miami = Location::factory()->create();
        $boston = Location::factory()->create();
        $admin = User::factory()->admin()->for($miami)->create();
        $closedStatus = SaleStatus::factory()->create([
            'name' => 'Closed',
            'slug' => 'closed',
        ]);

        Sale::factory()->closed()->withoutAgents()->recycle([$closedStatus, $miami])->create([
            'price' => '450000.00',
            'commission_percentage' => '3.0',
        ]);
        Sale::factory()->closed()->withoutAgents()->recycle([$closedStatus, $boston])->create([
            'price' => '200000.00',
            'commission_percentage' => '3.0',
        ]);

        Livewire::actingAs($admin)
            ->test(FirmSalesStatsOverview::class, [
                'pageFilters' => ['location_id' => $boston->id],
            ])
            ->assertSee('Closed sales')
            ->assertSee('1')
            ->assertSee('$450,000.00')
            ->assertDontSee('$200,000.00')
            ->assertDontSee('$650,000.00');
    }

    public function test_super_admin_can_filter_leads_by_location(): void
    {
        $miami = Location::factory()->create();
        $boston = Location::factory()->create();
        $admin = User::factory()->superAdmin()->create();
        $miamiLead = Lead::factory()->recycle($miami)->create();
        $bostonLead = Lead::factory()->recycle($boston)->create();

        Livewire::actingAs($admin)
            ->test(ListLeads::class)
            ->assertTableFilterVisible('location_id')
            ->filterTable('location_id', $miami->id)
            ->assertCanSeeTableRecords([$miamiLead])
            ->assertCanNotSeeTableRecords([$bostonLead]);
    }

    public function test_location_admin_does_not_see_the_lead_location_filter(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(ListLeads::class)
            ->assertTableFilterHidden('location_id');
    }

    public function test_super_admin_can_filter_sales_by_location(): void
    {
        $miami = Location::factory()->create();
        $boston = Location::factory()->create();
        $admin = User::factory()->superAdmin()->create();
        $miamiSale = Sale::factory()->recycle($miami)->create();
        $bostonSale = Sale::factory()->recycle($boston)->create();

        Livewire::actingAs($admin)
            ->test(ListSales::class)
            ->assertTableFilterVisible('location_id')
            ->filterTable('location_id', $miami->id)
            ->assertCanSeeTableRecords([$miamiSale])
            ->assertCanNotSeeTableRecords([$bostonSale]);
    }

    public function test_location_admin_does_not_see_the_sale_location_filter(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(ListSales::class)
            ->assertTableFilterHidden('location_id');
    }

    public function test_super_admin_can_filter_agents_by_location(): void
    {
        $miami = Location::factory()->create();
        $boston = Location::factory()->create();
        $admin = User::factory()->superAdmin()->create();
        $miamiAgent = User::factory()->for($miami)->create();
        $bostonAgent = User::factory()->for($boston)->create();

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->assertTableFilterVisible('location_id')
            ->filterTable('location_id', $miami->id)
            ->assertCanSeeTableRecords([$miamiAgent])
            ->assertCanNotSeeTableRecords([$bostonAgent, $admin]);
    }

    public function test_location_admin_does_not_see_the_agent_location_filter(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->assertTableFilterHidden('location_id');
    }
}
