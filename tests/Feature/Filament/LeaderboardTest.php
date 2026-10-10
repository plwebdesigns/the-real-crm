<?php

namespace Tests\Feature\Filament;

use App\Enums\LeaderboardPeriod;
use App\Filament\Pages\Leaderboard;
use App\Filament\Widgets\LeaderboardTable;
use App\Models\Location;
use App\Models\Sale;
use App\Models\SaleStatus;
use App\Models\SaleUser;
use App\Models\User;
use Filament\Pages\Dashboard;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LeaderboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_from_the_leaderboard(): void
    {
        $this->get(Leaderboard::getUrl(isAbsolute: false))->assertRedirect();
    }

    public function test_agent_can_view_the_leaderboard(): void
    {
        $this->travelTo('2026-09-14 12:00:00');

        $agent = User::factory()->create();

        $this->actingAs($agent)
            ->get(Leaderboard::getUrl(isAbsolute: false))
            ->assertOk()
            ->assertSeeLivewire(LeaderboardTable::class)
            ->assertSee('Q1 2026')
            ->assertSee('Q2 2026')
            ->assertSee('Q3 2026')
            ->assertSee('Year to date')
            ->assertSee('Jan 1 – Sep 14, 2026')
            ->assertDontSee('Q4 2026')
            ->assertSee('--col-span-lg: span 1 / span 1', false)
            ->assertSee('--col-span-lg: 1 / -1', false);
    }

    public function test_location_admin_can_view_the_leaderboard_without_a_location_filter(): void
    {
        $miami = Location::factory()->create(['name' => 'Miami Office']);
        $admin = User::factory()->admin()->for($miami)->create();

        Livewire::actingAs($admin)
            ->test(Leaderboard::class)
            ->assertDontSee('All locations')
            ->assertDontSee('Miami Office');
    }

    public function test_super_admin_sees_a_location_filter_on_the_leaderboard(): void
    {
        $miami = Location::factory()->create(['name' => 'Miami Office']);
        $admin = User::factory()->superAdmin()->create();

        Livewire::actingAs($admin)
            ->test(Leaderboard::class)
            ->assertSee('All locations')
            ->assertSee($miami->name);
    }

    public function test_personal_dashboard_does_not_include_the_leaderboard(): void
    {
        $agent = User::factory()->create();

        $this->actingAs($agent)
            ->get(Dashboard::getUrl(isAbsolute: false))
            ->assertOk()
            ->assertDontSeeLivewire(LeaderboardTable::class);
    }

    public function test_leaderboard_ranks_an_office_by_each_agents_share_of_closed_price(): void
    {
        $this->travelTo('2026-09-14 12:00:00');

        $miami = Location::factory()->create();
        $boston = Location::factory()->create();
        $admin = User::factory()->admin()->for($miami)->create(['name' => 'Riley Admin']);
        $partner = User::factory()->for($miami)->create(['name' => 'Jordan Agent']);
        $idleAgent = User::factory()->for($miami)->create(['name' => 'Idle Agent']);
        $bostonAgent = User::factory()->for($boston)->create(['name' => 'Bea Agent']);
        $closedStatus = $this->closedStatus();
        $pendingStatus = $this->pendingStatus();
        $splitSale = $this->closedSale($miami, $closedStatus, '500000.00', '2026-03-15');
        $splitSale->agents()->attach([
            $admin->id => [
                'commission_percent' => 60,
                'net_commission' => SaleUser::netCommissionFor(
                    Sale::remainingCommissionFor($splitSale->gross_commission, $splitSale->brokerage_fee),
                    60,
                ),
            ],
            $partner->id => [
                'commission_percent' => 40,
                'net_commission' => SaleUser::netCommissionFor(
                    Sale::remainingCommissionFor($splitSale->gross_commission, $splitSale->brokerage_fee),
                    40,
                ),
            ],
        ]);
        $this->assignAgent($this->closedSale($miami, $closedStatus, '100000.00', '2026-04-02'), $admin);
        $this->assignAgent(
            Sale::factory()->pending()->withoutAgents()->recycle([$pendingStatus, $miami])->create([
                'price' => '900000.00',
                'commission_percentage' => '3.0',
            ]),
            $admin,
        );
        $this->assignAgent($this->closedSale($boston, $closedStatus, '800000.00', '2026-03-20'), $bostonAgent);

        Livewire::actingAs($partner)
            ->test(LeaderboardTable::class, [
                'period' => LeaderboardPeriod::YearToDate,
            ])
            ->assertCanSeeTableRecords([$admin, $partner], inOrder: true)
            ->assertCanNotSeeTableRecords([$idleAgent, $bostonAgent])
            ->assertTableColumnStateSet('rank', 1, $admin)
            ->assertTableColumnStateSet('closed_volume', '400000.00', $admin)
            ->assertTableColumnStateSet('rank', 2, $partner)
            ->assertTableColumnStateSet('closed_volume', '200000.00', $partner)
            ->assertTableColumnHidden('location.name');
    }

    public function test_leaderboard_keeps_each_quarter_and_year_to_date_to_its_own_close_dates(): void
    {
        $this->travelTo('2026-09-14 12:00:00');

        $agent = User::factory()->create(['name' => 'Casey Agent']);
        $otherAgent = User::factory()->for($agent->location)->create(['name' => 'Quinn Agent']);
        $closedStatus = $this->closedStatus();
        $location = $agent->location;
        $this->assignAgent($this->closedSale($location, $closedStatus, '100000.00', '2026-02-10'), $agent);
        $this->assignAgent($this->closedSale($location, $closedStatus, '900000.00', '2025-11-01'), $agent);
        $this->assignAgent($this->closedSale($location, $closedStatus, '500000.00', '2026-12-01'), $agent);
        $this->assignAgent($this->closedSale($location, $closedStatus, '40000.00', '2026-05-10'), $otherAgent);
        $this->assignAgent($this->closedSale($location, $closedStatus, '30000.00', '2026-09-14'), $agent);
        $this->assignAgent($this->closedSale($location, $closedStatus, '70000.00', '2026-09-15'), $agent);

        Livewire::actingAs($agent)
            ->test(LeaderboardTable::class, [
                'period' => LeaderboardPeriod::FirstQuarter,
            ])
            ->assertCanSeeTableRecords([$agent])
            ->assertCanNotSeeTableRecords([$otherAgent])
            ->assertTableColumnStateSet('closed_volume', '100000.00', $agent);

        Livewire::actingAs($agent)
            ->test(LeaderboardTable::class, [
                'period' => LeaderboardPeriod::SecondQuarter,
            ])
            ->assertCanSeeTableRecords([$otherAgent])
            ->assertCanNotSeeTableRecords([$agent])
            ->assertTableColumnStateSet('closed_volume', '40000.00', $otherAgent);

        Livewire::actingAs($agent)
            ->test(LeaderboardTable::class, [
                'period' => LeaderboardPeriod::ThirdQuarter,
            ])
            ->assertCanSeeTableRecords([$agent])
            ->assertTableColumnStateSet('closed_volume', '30000.00', $agent);

        Livewire::actingAs($agent)
            ->test(LeaderboardTable::class, [
                'period' => LeaderboardPeriod::YearToDate,
            ])
            ->assertCanSeeTableRecords([$agent, $otherAgent], inOrder: true)
            ->assertTableColumnStateSet('closed_volume', '130000.00', $agent)
            ->assertTableColumnStateSet('closed_volume', '40000.00', $otherAgent);
    }

    public function test_leaderboard_shows_only_the_top_ten_agents(): void
    {
        $this->travelTo('2026-09-14 12:00:00');

        $location = Location::factory()->create();
        $viewer = User::factory()->for($location)->create();
        $closedStatus = $this->closedStatus();
        $agents = collect(range(1, 11))->map(function (int $place) use ($location, $closedStatus): User {
            $agent = User::factory()->for($location)->create([
                'name' => sprintf('Agent %02d', $place),
            ]);
            $this->assignAgent(
                $this->closedSale($location, $closedStatus, number_format((12 - $place) * 100000, 2, '.', ''), '2026-06-01'),
                $agent,
            );

            return $agent;
        });

        Livewire::actingAs($viewer)
            ->test(LeaderboardTable::class, [
                'period' => LeaderboardPeriod::YearToDate,
            ])
            ->assertCanSeeTableRecords($agents->take(10), inOrder: true)
            ->assertCanNotSeeTableRecords([$agents->last()])
            ->assertTableColumnStateSet('rank', 1, $agents->first())
            ->assertTableColumnStateSet('rank', 10, $agents->get(9));
    }

    public function test_tied_volume_ranks_agents_by_name(): void
    {
        $this->travelTo('2026-09-14 12:00:00');

        $location = Location::factory()->create();
        $ada = User::factory()->for($location)->create(['name' => 'Ada Agent']);
        $zoe = User::factory()->for($location)->create(['name' => 'Zoe Agent']);
        $closedStatus = $this->closedStatus();
        $this->assignAgent($this->closedSale($location, $closedStatus, '250000.00', '2026-03-01'), $zoe);
        $this->assignAgent($this->closedSale($location, $closedStatus, '250000.00', '2026-03-02'), $ada);

        Livewire::actingAs($ada)
            ->test(LeaderboardTable::class, [
                'period' => LeaderboardPeriod::YearToDate,
            ])
            ->assertCanSeeTableRecords([$ada, $zoe], inOrder: true)
            ->assertTableColumnStateSet('rank', 1, $ada)
            ->assertTableColumnStateSet('rank', 2, $zoe);
    }

    public function test_agent_cannot_view_another_offices_leaderboard_with_a_forged_location_filter(): void
    {
        $this->travelTo('2026-09-14 12:00:00');

        $miami = Location::factory()->create();
        $boston = Location::factory()->create();
        $miamiAgent = User::factory()->for($miami)->create(['name' => 'Mia Agent']);
        $bostonAgent = User::factory()->for($boston)->create(['name' => 'Bea Agent']);
        $closedStatus = $this->closedStatus();
        $this->assignAgent($this->closedSale($miami, $closedStatus, '150000.00', '2026-03-01'), $miamiAgent);
        $this->assignAgent($this->closedSale($boston, $closedStatus, '900000.00', '2026-03-01'), $bostonAgent);

        Livewire::actingAs($miamiAgent)
            ->test(LeaderboardTable::class, [
                'period' => LeaderboardPeriod::YearToDate,
                'pageFilters' => ['location_id' => $boston->id],
            ])
            ->assertCanSeeTableRecords([$miamiAgent])
            ->assertCanNotSeeTableRecords([$bostonAgent])
            ->assertTableColumnStateSet('closed_volume', '150000.00', $miamiAgent);
    }

    public function test_super_admin_can_limit_the_leaderboard_to_one_location(): void
    {
        $this->travelTo('2026-09-14 12:00:00');

        $miami = Location::factory()->create(['name' => 'Miami Office']);
        $boston = Location::factory()->create(['name' => 'Boston Office']);
        $admin = User::factory()->superAdmin()->create();
        $miamiAgent = User::factory()->for($miami)->create(['name' => 'Mia Agent']);
        $bostonAgent = User::factory()->for($boston)->create(['name' => 'Bea Agent']);
        $closedStatus = $this->closedStatus();
        $this->assignAgent($this->closedSale($miami, $closedStatus, '150000.00', '2026-03-01'), $miamiAgent);
        $this->assignAgent($this->closedSale($boston, $closedStatus, '900000.00', '2026-03-01'), $bostonAgent);

        Livewire::actingAs($admin)
            ->test(LeaderboardTable::class, [
                'period' => LeaderboardPeriod::YearToDate,
                'pageFilters' => ['location_id' => $miami->id],
            ])
            ->assertCanSeeTableRecords([$miamiAgent])
            ->assertCanNotSeeTableRecords([$bostonAgent])
            ->assertTableColumnHidden('location.name');
    }

    public function test_location_column_is_visible_when_a_super_admin_views_every_office(): void
    {
        $this->travelTo('2026-09-14 12:00:00');

        $miami = Location::factory()->create(['name' => 'Miami Office']);
        $boston = Location::factory()->create(['name' => 'Boston Office']);
        $admin = User::factory()->superAdmin()->create();
        $miamiAgent = User::factory()->for($miami)->create(['name' => 'Mia Agent']);
        $bostonAgent = User::factory()->for($boston)->create(['name' => 'Bea Agent']);
        $closedStatus = $this->closedStatus();
        $this->assignAgent($this->closedSale($miami, $closedStatus, '150000.00', '2026-03-01'), $miamiAgent);
        $this->assignAgent($this->closedSale($boston, $closedStatus, '900000.00', '2026-03-01'), $bostonAgent);

        Livewire::actingAs($admin)
            ->test(LeaderboardTable::class, [
                'period' => LeaderboardPeriod::YearToDate,
            ])
            ->assertCanSeeTableRecords([$bostonAgent, $miamiAgent], inOrder: true)
            ->assertTableColumnVisible('location.name')
            ->assertTableColumnStateSet('location.name', 'Boston Office', $bostonAgent)
            ->assertTableColumnStateSet('location.name', 'Miami Office', $miamiAgent);
    }

    private function closedSale(Location $location, SaleStatus $status, string $price, string $closedAt): Sale
    {
        return Sale::factory()->closed()->withoutAgents()->recycle([$status, $location])->create([
            'price' => $price,
            'commission_percentage' => '3.0',
            'closed_at' => $closedAt,
        ]);
    }

    private function assignAgent(Sale $sale, User $agent, int $percent = 100): Sale
    {
        $sale->agents()->attach($agent, [
            'commission_percent' => $percent,
            'net_commission' => SaleUser::netCommissionFor(
                Sale::remainingCommissionFor($sale->gross_commission, $sale->brokerage_fee),
                $percent,
            ),
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
