<?php

namespace Tests\Feature\Models;

use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\LeadStatus;
use App\Models\Sale;
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
}
