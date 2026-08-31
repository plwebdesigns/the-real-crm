<?php

namespace Tests\Feature\Models;

use App\Models\Lead;
use App\Models\LeadSource;
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
}
