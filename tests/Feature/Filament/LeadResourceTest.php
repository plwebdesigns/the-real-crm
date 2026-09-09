<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Resources\Leads\Pages\CreateLead;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\LeadStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LeadResourceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_from_leads(): void
    {
        $this->get(LeadResource::getUrl(isAbsolute: false))->assertRedirect();
    }

    public function test_agent_can_view_leads(): void
    {
        $agent = User::factory()->create();

        $this->actingAs($agent)
            ->get(LeadResource::getUrl(isAbsolute: false))
            ->assertOk();
    }

    public function test_agent_can_create_a_lead(): void
    {
        $agent = User::factory()->create();
        $status = LeadStatus::factory()->create();
        $source = LeadSource::factory()->create();

        Livewire::actingAs($agent)
            ->test(CreateLead::class)
            ->fillForm([
                'first_name' => 'Ada',
                'last_name' => 'Lovelace',
                'email' => 'ada@example.com',
                'phone' => '555-0100',
                'lead_status_id' => $status->id,
                'lead_source_id' => $source->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Lead::class, [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.com',
            'lead_status_id' => $status->id,
            'lead_source_id' => $source->id,
        ]);
    }

    public function test_agent_can_create_a_lead_with_optional_property_fields(): void
    {
        $agent = User::factory()->create();
        $status = LeadStatus::factory()->create();
        $source = LeadSource::factory()->create();

        Livewire::actingAs($agent)
            ->test(CreateLead::class)
            ->fillForm([
                'first_name' => 'Ada',
                'last_name' => 'Lovelace',
                'lead_status_id' => $status->id,
                'lead_source_id' => $source->id,
                'location' => 'Austin',
                'property_type' => 'Single Family',
                'price_range' => '$300k-$500k',
                'bedrooms' => 3,
                'bathrooms' => '2.5',
                'garage' => true,
                'pool' => false,
                'notes' => 'Prefers a quiet street.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $lead = Lead::query()->where('first_name', 'Ada')->first();

        $this->assertNotNull($lead);
        $this->assertSame('Austin', $lead->location);
        $this->assertSame('Single Family', $lead->property_type);
        $this->assertSame('$300k-$500k', $lead->price_range);
        $this->assertSame(3, $lead->bedrooms);
        $this->assertSame('2.5', $lead->bathrooms);
        $this->assertSame(true, $lead->garage);
        $this->assertSame(false, $lead->pool);
        $this->assertSame('Prefers a quiet street.', $lead->notes);
    }
}
