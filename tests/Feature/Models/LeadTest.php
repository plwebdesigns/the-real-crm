<?php

namespace Tests\Feature\Models;

use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\LeadStatus;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class LeadTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_lead_can_have_multiple_sales(): void
    {
        $lead = Lead::factory()->create();

        $sales = Sale::factory()->count(2)->for($lead)->create();

        $this->assertCount(2, $lead->sales);
        $this->assertTrue($lead->sales->contains($sales[0]));
        $this->assertTrue($lead->sales->contains($sales[1]));
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
}
