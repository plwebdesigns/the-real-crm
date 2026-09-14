<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\LeadStatus;
use App\Models\Sale;
use App\Models\SaleStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sample_leads_and_sales_reuse_lookup_statuses_and_sources(): void
    {
        $this->seed();

        $this->assertSame([
            'contacted',
            'lost',
            'new',
            'qualified',
        ], LeadStatus::query()->orderBy('slug')->pluck('slug')->all());
        $this->assertSame([
            'open-house',
            'other',
            'referral',
            'website',
            'zillow',
        ], LeadSource::query()->orderBy('slug')->pluck('slug')->all());
        $this->assertSame([
            'cancelled',
            'closed',
            'pending',
        ], SaleStatus::query()->orderBy('slug')->pluck('slug')->all());
        $this->assertDatabaseCount(Lead::class, 30);
        $this->assertDatabaseCount(Sale::class, 10);
    }
}
