<?php

namespace Tests\Feature\Models;

use App\Enums\BrokerageFeeType;
use App\Enums\SaleType;
use App\Models\Lead;
use App\Models\Location;
use App\Models\Sale;
use App\Models\SaleStatus;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SaleTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sale_type_matches_the_lead_type(): void
    {
        $lead = Lead::factory()->create(['type' => SaleType::Buyer]);

        $sale = Sale::factory()->for($lead)->create([
            'sale_type' => SaleType::Seller,
        ]);

        $this->assertSame(SaleType::Buyer, $sale->sale_type);
    }

    public function test_sale_cannot_be_persisted_without_a_lead(): void
    {
        $this->expectException(QueryException::class);

        Sale::query()->create([
            'street_address' => '123 Main St',
            'city' => 'Austin',
            'state' => 'TX',
            'postal_code' => '78701',
            'price' => '450000.00',
            'commission_percentage' => '3.0',
            'sale_type' => SaleType::Seller,
            'sale_status_id' => SaleStatus::factory()->create()->id,
        ]);
    }

    public function test_sale_cannot_be_persisted_without_a_status(): void
    {
        $this->expectException(QueryException::class);

        Sale::factory()->withoutAgents()->create([
            'sale_status_id' => null,
        ]);
    }

    public function test_sale_calculates_gross_commission_from_price_and_percentage(): void
    {
        $sale = Sale::factory()->withoutAgents()->create([
            'price' => '450000.00',
            'commission_percentage' => '3.0',
        ]);

        $this->assertSame('13500.00', $sale->gross_commission);
        $this->assertSame('250.00', $sale->brokerage_fee);
    }

    public function test_percent_brokerage_fee_is_deducted_before_agent_splits(): void
    {
        config([
            'app.brokerage_fee' => 10,
            'app.brokerage_fee_type' => BrokerageFeeType::Percent->value,
        ]);

        $sale = Sale::factory()->withoutAgents()->create([
            'price' => '450000.00',
            'commission_percentage' => '3.0',
        ]);
        $listingAgent = User::factory()->create();
        $buyersAgent = User::factory()->create();

        $sale->agents()->attach($listingAgent, ['commission_percent' => 50]);
        $sale->agents()->attach($buyersAgent, ['commission_percent' => 50]);

        $sale->load('agents');

        $this->assertSame('13500.00', $sale->gross_commission);
        $this->assertSame('1350.00', $sale->brokerage_fee);
        $this->assertSame('6075.00', $sale->agents->find($listingAgent->id)?->pivot->net_commission);
        $this->assertSame('6075.00', $sale->agents->find($buyersAgent->id)?->pivot->net_commission);
    }

    public function test_brokerage_fee_larger_than_gross_is_clamped_and_agents_receive_zero(): void
    {
        config([
            'app.brokerage_fee' => 20000,
            'app.brokerage_fee_type' => BrokerageFeeType::Fixed->value,
        ]);

        $sale = Sale::factory()->withoutAgents()->create([
            'price' => '450000.00',
            'commission_percentage' => '3.0',
        ]);
        $agent = User::factory()->create();

        $sale->agents()->attach($agent, ['commission_percent' => 100]);

        $sale->load('agents');

        $this->assertSame('13500.00', $sale->gross_commission);
        $this->assertSame('13500.00', $sale->brokerage_fee);
        $this->assertSame('0.00', $sale->agents->first()?->pivot->net_commission);
    }

    public function test_sale_can_have_multiple_agents_with_commission_splits(): void
    {
        $sale = Sale::factory()->withoutAgents()->create([
            'price' => '450000.00',
            'commission_percentage' => '3.0',
        ]);
        $listingAgent = User::factory()->create();
        $buyersAgent = User::factory()->create();

        $sale->agents()->attach($listingAgent, ['commission_percent' => 50]);
        $sale->agents()->attach($buyersAgent, ['commission_percent' => 50]);

        $sale->load('agents');

        $this->assertCount(2, $sale->agents);
        $this->assertSame(50, $sale->agents->find($listingAgent->id)?->pivot->commission_percent);
        $this->assertSame(50, $sale->agents->find($buyersAgent->id)?->pivot->commission_percent);
        $this->assertSame('6625.00', $sale->agents->find($listingAgent->id)?->pivot->net_commission);
        $this->assertSame('6625.00', $sale->agents->find($buyersAgent->id)?->pivot->net_commission);
    }

    public function test_updating_sale_price_recalculates_agent_net_commissions(): void
    {
        $sale = Sale::factory()->withoutAgents()->create([
            'price' => '450000.00',
            'commission_percentage' => '3.0',
        ]);
        $listingAgent = User::factory()->create();
        $buyersAgent = User::factory()->create();

        $sale->agents()->attach($listingAgent, ['commission_percent' => 50]);
        $sale->agents()->attach($buyersAgent, ['commission_percent' => 50]);

        $sale->update(['price' => '500000.00']);
        $sale->load('agents');

        $this->assertSame('15000.00', $sale->gross_commission);
        $this->assertSame('250.00', $sale->brokerage_fee);
        $this->assertSame('7375.00', $sale->agents->find($listingAgent->id)?->pivot->net_commission);
        $this->assertSame('7375.00', $sale->agents->find($buyersAgent->id)?->pivot->net_commission);
    }

    public function test_factory_created_sale_has_at_least_one_agent(): void
    {
        $sale = Sale::factory()->create([
            'price' => '450000.00',
            'commission_percentage' => '3.0',
        ]);

        $this->assertCount(1, $sale->agents);
        $this->assertSame(100, $sale->agents->first()?->pivot->commission_percent);
        $this->assertSame('13250.00', $sale->agents->first()?->pivot->net_commission);
    }

    public function test_factory_fills_closed_at_when_status_is_closed(): void
    {
        $this->travelTo('2026-09-14 12:00:00');

        $status = SaleStatus::factory()->create([
            'name' => 'Closed',
            'slug' => 'closed',
        ]);

        $sale = Sale::factory()->recycle($status)->create();

        $this->assertSame('2026-09-14', $sale->fresh()->closed_at?->toDateString());
    }

    public function test_factory_preserves_an_explicit_closed_at_date(): void
    {
        $this->travelTo('2026-09-14 12:00:00');

        $status = SaleStatus::factory()->create([
            'name' => 'Closed',
            'slug' => 'closed',
        ]);

        $sale = Sale::factory()->recycle($status)->create([
            'closed_at' => '2026-03-15',
        ]);

        $this->assertSame('2026-03-15', $sale->fresh()->closed_at?->toDateString());
    }

    public function test_factory_does_not_fill_closed_at_when_status_is_not_closed(): void
    {
        $sale = Sale::factory()->pending()->create();

        $this->assertNull($sale->fresh()->closed_at);
    }

    public function test_same_agent_cannot_be_attached_to_a_sale_twice(): void
    {
        $sale = Sale::factory()->withoutAgents()->create();
        $agent = User::factory()->create();

        $sale->agents()->attach($agent, ['commission_percent' => 100]);

        $this->expectException(QueryException::class);

        $sale->agents()->attach($agent, ['commission_percent' => 50]);
    }

    public function test_non_admin_sees_only_assigned_sales_at_their_location(): void
    {
        $miami = Location::factory()->create();
        $boston = Location::factory()->create();
        $agent = User::factory()->for($miami)->create();
        $coworker = User::factory()->for($miami)->create();
        $ownSale = Sale::factory()->withoutAgents()->recycle($miami)->create();
        $ownSale->agents()->attach($agent, ['commission_percent' => 100]);
        $sharedSale = Sale::factory()->withoutAgents()->recycle($miami)->create();
        $sharedSale->agents()->attach($agent, ['commission_percent' => 50]);
        $sharedSale->agents()->attach($coworker, ['commission_percent' => 50]);
        $coworkerSale = Sale::factory()->withoutAgents()->recycle($miami)->create();
        $coworkerSale->agents()->attach($coworker, ['commission_percent' => 100]);
        Sale::factory()->withoutAgents()->recycle($miami)->create();
        $otherLocationSale = Sale::factory()->withoutAgents()->recycle($boston)->create();
        $otherLocationSale->agents()->attach($agent, ['commission_percent' => 100]);

        $this->assertSame(
            [$ownSale->id, $sharedSale->id],
            Sale::query()->visibleTo($agent)->orderBy('id')->pluck('id')->all(),
        );
    }

    public function test_location_admin_sees_every_sale_at_their_location(): void
    {
        $miami = Location::factory()->create();
        $boston = Location::factory()->create();
        $admin = User::factory()->admin()->for($miami)->create();
        $agent = User::factory()->for($miami)->create();
        $assignedSale = Sale::factory()->withoutAgents()->recycle($miami)->create();
        $assignedSale->agents()->attach($agent, ['commission_percent' => 100]);
        $unassignedSale = Sale::factory()->withoutAgents()->recycle($miami)->create();
        Sale::factory()->withoutAgents()->recycle($boston)->create();

        $this->assertSame(
            [$assignedSale->id, $unassignedSale->id],
            Sale::query()->visibleTo($admin)->orderBy('id')->pluck('id')->all(),
        );
    }

    public function test_super_admin_sees_sales_at_every_location(): void
    {
        $miami = Location::factory()->create();
        $boston = Location::factory()->create();
        $admin = User::factory()->superAdmin()->create();
        $miamiSale = Sale::factory()->withoutAgents()->recycle($miami)->create();
        $bostonSale = Sale::factory()->withoutAgents()->recycle($boston)->create();

        $this->assertSame(
            [$miamiSale->id, $bostonSale->id],
            Sale::query()->visibleTo($admin)->orderBy('id')->pluck('id')->all(),
        );
    }
}
