<?php

namespace Tests\Feature\Models;

use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Sale;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class LeadSourceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_source_in_use_cannot_be_deleted(): void
    {
        $source = LeadSource::factory()->create();
        Lead::factory()->for($source, 'source')->create();

        $this->expectException(QueryException::class);

        $source->delete();
    }

    public function test_source_has_sales_through_its_leads(): void
    {
        $source = LeadSource::factory()->create();
        $lead = Lead::factory()->for($source, 'source')->create();
        $secondLead = Lead::factory()->for($source, 'source')->create();
        $firstSale = Sale::factory()->for($lead)->create();
        $secondSale = Sale::factory()->for($secondLead)->create();

        $this->assertCount(2, $source->sales);
        $this->assertTrue($source->sales->contains($firstSale));
        $this->assertTrue($source->sales->contains($secondSale));
    }
}
