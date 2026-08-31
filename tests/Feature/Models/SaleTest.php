<?php

namespace Tests\Feature\Models;

use App\Models\Sale;
use App\Models\SaleStatus;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SaleTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sale_cannot_be_persisted_without_a_lead(): void
    {
        $this->expectException(QueryException::class);

        Sale::query()->create([
            'street_address' => '123 Main St',
            'city' => 'Austin',
            'state' => 'TX',
            'postal_code' => '78701',
            'price' => '450000.00',
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

    public function test_sale_can_have_multiple_agents_with_commission_splits(): void
    {
        $sale = Sale::factory()->withoutAgents()->create();
        $listingAgent = User::factory()->create();
        $buyersAgent = User::factory()->create();

        $sale->agents()->attach($listingAgent, ['commission_percent' => '2.50']);
        $sale->agents()->attach($buyersAgent, ['commission_percent' => '3.00']);

        $sale->load('agents');

        $this->assertCount(2, $sale->agents);
        $this->assertSame('2.50', $sale->agents->find($listingAgent->id)?->pivot->commission_percent);
        $this->assertSame('3.00', $sale->agents->find($buyersAgent->id)?->pivot->commission_percent);
    }

    public function test_factory_created_sale_has_at_least_one_agent(): void
    {
        $sale = Sale::factory()->create();

        $this->assertCount(1, $sale->agents);
        $this->assertSame('3.00', $sale->agents->first()?->pivot->commission_percent);
    }

    public function test_same_agent_cannot_be_attached_to_a_sale_twice(): void
    {
        $sale = Sale::factory()->withoutAgents()->create();
        $agent = User::factory()->create();

        $sale->agents()->attach($agent, ['commission_percent' => '3.00']);

        $this->expectException(QueryException::class);

        $sale->agents()->attach($agent, ['commission_percent' => '1.00']);
    }
}
