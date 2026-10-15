<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\LeadStatus;
use App\Models\Sale;
use App\Models\SaleStatus;
use App\Models\User;
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
            'converted',
            'lost',
            'new',
            'qualified',
        ], LeadStatus::query()->orderBy('slug')->pluck('slug')->all());
        $this->assertSame([
            'car-ads',
            'facebook',
            'instagram',
            'linkedin',
            'open-house',
            'referral',
            'twitter',
            'website',
            'youtube',
            'zillow',
        ], LeadSource::query()->orderBy('slug')->pluck('slug')->all());
        $this->assertSame([
            'cancelled',
            'closed',
            'pending',
        ], SaleStatus::query()->orderBy('slug')->pluck('slug')->all());
        $this->assertDatabaseCount(Lead::class, 550);
        $this->assertDatabaseCount(Sale::class, 132);
    }

    public function test_each_agent_has_leads_in_every_quarter_through_the_current_quarter(): void
    {
        $this->travelTo('2026-10-11 15:00:00');

        $this->seed();

        $quarters = [
            1 => ['2026-01-01 00:00:00', '2026-03-31 23:59:59'],
            2 => ['2026-04-01 00:00:00', '2026-06-30 23:59:59'],
            3 => ['2026-07-01 00:00:00', '2026-09-30 23:59:59'],
            4 => ['2026-10-01 00:00:00', '2026-10-11 15:00:00'],
        ];

        $agents = User::query()->whereNotNull('location_id')->get();

        $this->assertCount(11, $agents);
        $this->assertSame(0, Lead::query()->where('created_at', '<', '2026-01-01 00:00:00')->count());
        $this->assertSame(0, Lead::query()->where('created_at', '>', '2026-10-11 15:00:00')->count());

        foreach ($agents as $agent) {
            foreach ($quarters as $quarter => [$start, $end]) {
                $this->assertGreaterThan(
                    0,
                    $agent->leads()->whereBetween('leads.created_at', [$start, $end])->count(),
                    "{$agent->email} has no lead in Q{$quarter} 2026.",
                );
            }
        }
    }

    public function test_each_agent_has_a_sale_in_every_quarter_of_the_current_year(): void
    {
        $this->travelTo('2026-10-11 15:00:00');

        $this->seed();

        $quarters = [
            1 => ['2026-01-01 00:00:00', '2026-03-31 23:59:59'],
            2 => ['2026-04-01 00:00:00', '2026-06-30 23:59:59'],
            3 => ['2026-07-01 00:00:00', '2026-09-30 23:59:59'],
            4 => ['2026-10-01 00:00:00', '2026-10-11 15:00:00'],
        ];

        $agents = User::query()->whereNotNull('location_id')->get();

        $this->assertSame(0, Sale::query()->where('created_at', '<', '2026-01-01 00:00:00')->count());
        $this->assertSame(0, Sale::query()->where('created_at', '>', '2026-10-11 15:00:00')->count());

        foreach ($agents as $agent) {
            $this->assertSame(12, $agent->sales()->count());

            foreach ($quarters as $quarter => [$start, $end]) {
                $this->assertGreaterThan(
                    0,
                    $agent->sales()->whereBetween('sales.created_at', [$start, $end])->count(),
                    "{$agent->email} has no sale in Q{$quarter} 2026.",
                );
            }
        }
    }
}
