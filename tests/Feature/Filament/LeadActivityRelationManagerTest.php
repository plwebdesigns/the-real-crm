<?php

namespace Tests\Feature\Filament;

use App\Enums\LeadActivityType;
use App\Filament\Resources\Leads\Pages\EditLead;
use App\Filament\Resources\Leads\Pages\ListLeads;
use App\Filament\Resources\Leads\RelationManagers\ActivitiesRelationManager;
use App\Filament\Widgets\AssignedLeadsTable;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LeadActivityRelationManagerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_agent_can_log_an_activity_on_a_lead_at_their_office(): void
    {
        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $otherAgent = User::factory()->for($location)->create();
        $lead = Lead::factory()->recycle($location)->create([
            'notes' => 'Prefers a quiet street.',
        ]);

        Livewire::actingAs($agent)
            ->test(ActivitiesRelationManager::class, [
                'ownerRecord' => $lead,
                'pageClass' => EditLead::class,
            ])
            ->callTableAction('create', data: [
                'type' => LeadActivityType::Call->value,
                'body' => 'Left a voicemail.',
                'happened_at' => '2026-09-20 15:30:00',
                'user_id' => $otherAgent->id,
            ])
            ->assertHasNoTableActionErrors();

        $activity = $lead->activities()->first();

        $this->assertNotNull($activity);
        $this->assertSame($agent->id, $activity->user_id);
        $this->assertSame(LeadActivityType::Call, $activity->type);
        $this->assertSame('Left a voicemail.', $activity->body);
        $this->assertSame('2026-09-20 15:30:00', $activity->happened_at->toDateTimeString());
        $this->assertSame('Prefers a quiet street.', $lead->fresh()->notes);
    }

    public function test_activity_requires_type_body_and_when_it_happened(): void
    {
        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $lead = Lead::factory()->recycle($location)->create();

        Livewire::actingAs($agent)
            ->test(ActivitiesRelationManager::class, [
                'ownerRecord' => $lead,
                'pageClass' => EditLead::class,
            ])
            ->callTableAction('create', data: [
                'type' => null,
                'body' => null,
                'happened_at' => null,
            ])
            ->assertHasTableActionErrors([
                'type' => 'required',
                'body' => 'required',
                'happened_at' => 'required',
            ]);

        $this->assertSame(0, $lead->activities()->count());
    }

    public function test_agent_cannot_open_activities_for_a_lead_at_another_office(): void
    {
        $agent = User::factory()->create();
        $lead = Lead::factory()->create();

        Livewire::actingAs($agent)
            ->test(ActivitiesRelationManager::class, [
                'ownerRecord' => $lead,
                'pageClass' => EditLead::class,
            ])
            ->assertForbidden();

        $this->assertSame(0, LeadActivity::query()->count());
    }

    public function test_leads_table_shows_last_touched_from_the_latest_activity(): void
    {
        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $lead = $this->assignLead(Lead::factory()->recycle($location)->create(), $agent);
        LeadActivity::factory()->for($lead)->for($agent)->create([
            'happened_at' => '2026-01-15 08:05:00',
        ]);
        LeadActivity::factory()->for($lead)->for($agent)->create([
            'happened_at' => '2025-11-02 09:00:00',
        ]);

        Livewire::actingAs($agent)
            ->test(ListLeads::class)
            ->assertSee('Jan 15, 2026 08:05:00')
            ->assertDontSee('Nov 2, 2025 09:00:00');
    }

    public function test_my_leads_shows_last_touched_from_the_latest_activity(): void
    {
        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $lead = $this->assignLead(Lead::factory()->recycle($location)->create(), $agent);
        LeadActivity::factory()->for($lead)->for($agent)->create([
            'happened_at' => '2026-01-15 08:05:00',
        ]);
        LeadActivity::factory()->for($lead)->for($agent)->create([
            'happened_at' => '2025-11-02 09:00:00',
        ]);

        Livewire::actingAs($agent)
            ->test(AssignedLeadsTable::class)
            ->assertSee('Jan 15, 2026 08:05:00')
            ->assertDontSee('Nov 2, 2025 09:00:00');
    }

    private function assignLead(Lead $lead, User $agent): Lead
    {
        $lead->agents()->attach($agent);

        return $lead;
    }
}
