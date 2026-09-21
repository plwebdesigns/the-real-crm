<?php

namespace Tests\Feature\Models;

use App\Enums\SaleType;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\LeadStatus;
use App\Models\Sale;
use App\Models\SaleStatus;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class LeadTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_lead_cannot_have_more_than_one_sale(): void
    {
        $lead = Lead::factory()->create();
        Sale::factory()->for($lead)->create();

        $this->expectException(QueryException::class);

        Sale::factory()->for($lead)->create();
    }

    public function test_lead_has_one_sale(): void
    {
        $lead = Lead::factory()->create();
        $sale = Sale::factory()->for($lead)->create();

        $this->assertTrue($lead->sale->is($sale));
    }

    public function test_lead_belongs_to_a_status_and_source(): void
    {
        $status = LeadStatus::factory()->create();
        $source = LeadSource::factory()->create();

        $lead = Lead::factory()->for($status, 'status')->for($source, 'source')->create();

        $this->assertTrue($lead->status->is($status));
        $this->assertTrue($lead->source->is($source));
    }

    public function test_lead_cannot_be_persisted_without_a_status(): void
    {
        $this->expectException(QueryException::class);

        Lead::query()->create([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'lead_source_id' => LeadSource::factory()->create()->id,
        ]);
    }

    public function test_lead_cannot_be_persisted_without_a_source(): void
    {
        $this->expectException(QueryException::class);

        Lead::query()->create([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'lead_status_id' => LeadStatus::factory()->create()->id,
        ]);
    }

    public function test_lead_can_be_persisted_without_optional_property_fields(): void
    {
        $lead = Lead::factory()->create([
            'location' => null,
            'property_type' => null,
            'price_range' => null,
            'bedrooms' => null,
            'bathrooms' => null,
            'garage' => null,
            'pool' => null,
            'notes' => null,
        ]);

        $this->assertNull($lead->location);
        $this->assertNull($lead->property_type);
        $this->assertNull($lead->price_range);
        $this->assertNull($lead->bedrooms);
        $this->assertNull($lead->bathrooms);
        $this->assertNull($lead->garage);
        $this->assertNull($lead->pool);
        $this->assertNull($lead->notes);
    }

    public function test_lead_can_have_multiple_agents(): void
    {
        $lead = Lead::factory()->create();
        $listingAgent = User::factory()->create();
        $buyersAgent = User::factory()->create();

        $lead->agents()->attach([$listingAgent->id, $buyersAgent->id]);

        $lead->load('agents');

        $this->assertCount(2, $lead->agents);
        $this->assertTrue($lead->agents->contains($listingAgent));
        $this->assertTrue($lead->agents->contains($buyersAgent));
    }

    public function test_same_agent_cannot_be_attached_to_a_lead_twice(): void
    {
        $lead = Lead::factory()->create();
        $agent = User::factory()->create();

        $lead->agents()->attach($agent);

        $this->expectException(QueryException::class);

        $lead->agents()->attach($agent);
    }

    public function test_working_scope_includes_contacted_and_qualified_leads(): void
    {
        $contacted = Lead::factory()->contacted()->create();
        $qualified = Lead::factory()->qualified()->create();
        Lead::factory()->asNew()->create();
        Lead::factory()->lost()->create();

        $this->assertSame(
            [$contacted->id, $qualified->id],
            Lead::query()->working()->orderBy('id')->pluck('id')->all(),
        );
    }

    public function test_lost_scope_includes_lost_leads(): void
    {
        $lost = Lead::factory()->lost()->create();
        Lead::factory()->contacted()->create();

        $this->assertSame(
            [$lost->id],
            Lead::query()->lost()->orderBy('id')->pluck('id')->all(),
        );
    }

    public function test_closed_scope_includes_leads_with_a_closed_sale(): void
    {
        $closedLead = Lead::factory()->create();
        Sale::factory()->closed()->for($closedLead)->create();
        $pendingLead = Lead::factory()->create();
        Sale::factory()->pending()->for($pendingLead)->create();
        Lead::factory()->create();

        $this->assertSame(
            [$closedLead->id],
            Lead::query()->closed()->orderBy('id')->pluck('id')->all(),
        );
    }

    public function test_closed_scope_counts_each_lead_when_the_same_person_has_two_closed_sales(): void
    {
        $closedStatus = SaleStatus::factory()->create([
            'name' => 'Closed',
            'slug' => 'closed',
        ]);
        $seller = Lead::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'type' => SaleType::Seller,
        ]);
        $buyer = Lead::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'type' => SaleType::Buyer,
        ]);
        Sale::factory()->closed()->recycle($closedStatus)->for($seller)->create();
        Sale::factory()->closed()->recycle($closedStatus)->for($buyer)->create();

        $this->assertSame(
            [$seller->id, $buyer->id],
            Lead::query()->closed()->orderBy('id')->pluck('id')->all(),
        );
    }

    public function test_lead_cannot_be_persisted_without_a_type(): void
    {
        $this->expectException(QueryException::class);

        Lead::query()->create([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'lead_status_id' => LeadStatus::factory()->create()->id,
            'lead_source_id' => LeadSource::factory()->create()->id,
        ]);
    }

    public function test_related_type_is_the_opposite_side_for_buyer_and_seller(): void
    {
        $seller = Lead::factory()->create(['type' => SaleType::Seller]);
        $rental = Lead::factory()->create(['type' => SaleType::Rental]);

        $this->assertSame(SaleType::Buyer, $seller->relatedType());
        $this->assertSame(SaleType::Rental, $rental->relatedType());
    }
}
