<?php

namespace Tests\Feature\Models;

use App\Models\Lead;
use App\Models\LeadActivity;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class LeadActivityTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_latest_activity_is_the_event_with_the_latest_happened_at(): void
    {
        $lead = Lead::factory()->create();
        $latest = LeadActivity::factory()->for($lead)->create([
            'happened_at' => '2026-09-20 15:00:00',
        ]);
        LeadActivity::factory()->for($lead)->create([
            'happened_at' => '2026-09-01 09:00:00',
        ]);

        $this->assertTrue($lead->latestActivity->is($latest));
    }

    public function test_latest_activity_breaks_a_happened_at_tie_on_id(): void
    {
        $lead = Lead::factory()->create();
        LeadActivity::factory()->for($lead)->create([
            'happened_at' => '2026-09-20 15:00:00',
        ]);
        $laterInsert = LeadActivity::factory()->for($lead)->create([
            'happened_at' => '2026-09-20 15:00:00',
        ]);

        $this->assertTrue($lead->latestActivity->is($laterInsert));
    }

    public function test_leads_order_by_the_latest_activity_time(): void
    {
        $touchedEarlier = Lead::factory()->create();
        $touchedLater = Lead::factory()->create();
        LeadActivity::factory()->for($touchedEarlier)->create([
            'happened_at' => '2026-09-01 09:00:00',
        ]);
        LeadActivity::factory()->for($touchedLater)->create([
            'happened_at' => '2026-09-20 15:00:00',
        ]);

        $orderedIds = Lead::query()->orderByLastTouched('desc')->pluck('id')->all();

        $this->assertSame([$touchedLater->id, $touchedEarlier->id], $orderedIds);
    }

    public function test_deleting_a_lead_deletes_its_activities(): void
    {
        $lead = Lead::factory()->create();
        $activity = LeadActivity::factory()->for($lead)->create();

        $lead->delete();

        $this->assertModelMissing($activity);
    }
}
