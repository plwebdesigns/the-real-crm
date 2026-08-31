<?php

namespace Tests\Feature\Models;

use App\Models\Lead;
use App\Models\LeadStatus;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class LeadStatusTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_status_in_use_cannot_be_deleted(): void
    {
        $status = LeadStatus::factory()->create();
        Lead::factory()->for($status, 'status')->create();

        $this->expectException(QueryException::class);

        $status->delete();
    }
}
