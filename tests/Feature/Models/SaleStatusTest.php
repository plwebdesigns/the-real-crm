<?php

namespace Tests\Feature\Models;

use App\Models\Sale;
use App\Models\SaleStatus;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SaleStatusTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_status_in_use_cannot_be_deleted(): void
    {
        $status = SaleStatus::factory()->create();
        Sale::factory()->for($status, 'status')->create();

        $this->expectException(QueryException::class);

        $status->delete();
    }
}
