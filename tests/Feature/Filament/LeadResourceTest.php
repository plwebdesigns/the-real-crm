<?php

namespace Tests\Feature\Filament;

use App\Enums\SaleType;
use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Resources\Leads\Pages\CreateLead;
use App\Filament\Resources\Leads\Pages\EditLead;
use App\Filament\Resources\Leads\Pages\ListLeads;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\LeadStatus;
use App\Models\Location;
use App\Models\Sale;
use App\Models\SaleStatus;
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
                'type' => SaleType::Buyer,
                'lead_status_id' => $status->id,
                'lead_source_id' => $source->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Lead::class, [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.com',
            'type' => SaleType::Buyer,
            'lead_status_id' => $status->id,
            'lead_source_id' => $source->id,
            'location_id' => $agent->location_id,
        ]);

        $lead = Lead::query()->where('first_name', 'Ada')->first();

        $this->assertNotNull($lead);
        $this->assertCount(1, $lead->agents);
        $this->assertTrue($lead->agents->contains($agent));
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
                'type' => SaleType::Seller,
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
        $this->assertSame(SaleType::Seller, $lead->type);
        $this->assertSame('Austin', $lead->location);
        $this->assertSame($agent->location_id, $lead->location_id);
        $this->assertSame('Single Family', $lead->property_type);
        $this->assertSame('$300k-$500k', $lead->price_range);
        $this->assertSame(3, $lead->bedrooms);
        $this->assertSame('2.5', $lead->bathrooms);
        $this->assertSame(true, $lead->garage);
        $this->assertSame(false, $lead->pool);
        $this->assertSame('Prefers a quiet street.', $lead->notes);
    }

    public function test_agent_can_assign_multiple_agents_when_creating_a_lead(): void
    {
        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $listingAgent = User::factory()->for($location)->create();
        $buyersAgent = User::factory()->for($location)->create();
        $status = LeadStatus::factory()->create();
        $source = LeadSource::factory()->create();

        Livewire::actingAs($agent)
            ->test(CreateLead::class)
            ->fillForm([
                'first_name' => 'Ada',
                'last_name' => 'Lovelace',
                'type' => SaleType::Buyer,
                'lead_status_id' => $status->id,
                'lead_source_id' => $source->id,
                'agents' => [$listingAgent->id, $buyersAgent->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $lead = Lead::query()->where('first_name', 'Ada')->first();

        $this->assertNotNull($lead);
        $this->assertCount(2, $lead->agents);
        $this->assertTrue($lead->agents->contains($listingAgent));
        $this->assertTrue($lead->agents->contains($buyersAgent));
        $this->assertFalse($lead->agents->contains($agent));
    }

    public function test_lead_requires_a_type(): void
    {
        $agent = User::factory()->create();
        $status = LeadStatus::factory()->create();
        $source = LeadSource::factory()->create();

        Livewire::actingAs($agent)
            ->test(CreateLead::class)
            ->fillForm([
                'first_name' => 'Ada',
                'last_name' => 'Lovelace',
                'type' => null,
                'lead_status_id' => $status->id,
                'lead_source_id' => $source->id,
            ])
            ->call('create')
            ->assertHasFormErrors(['type']);

        $this->assertDatabaseMissing(Lead::class, [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
        ]);
    }

    public function test_agent_can_update_assigned_agents_on_a_lead(): void
    {
        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $originalAgent = User::factory()->for($location)->create();
        $replacementAgent = User::factory()->for($location)->create();
        $lead = Lead::factory()->recycle($location)->create();
        $lead->agents()->attach($originalAgent);

        Livewire::actingAs($agent)
            ->test(EditLead::class, ['record' => $lead->getRouteKey()])
            ->fillForm([
                'agents' => [$replacementAgent->id],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $lead->refresh()->load('agents');

        $this->assertCount(1, $lead->agents);
        $this->assertTrue($lead->agents->contains($replacementAgent));
        $this->assertFalse($lead->agents->contains($originalAgent));
    }

    public function test_working_tab_shows_contacted_and_qualified_leads_for_the_selected_agent(): void
    {
        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $otherAgent = User::factory()->for($location)->create();
        $contacted = $this->assignLead(Lead::factory()->contacted()->recycle($location)->create(), $agent);
        $qualified = $this->assignLead(Lead::factory()->qualified()->recycle($location)->create(), $agent);
        $new = $this->assignLead(Lead::factory()->asNew()->recycle($location)->create(), $agent);
        $lost = $this->assignLead(Lead::factory()->lost()->recycle($location)->create(), $agent);
        $otherContacted = $this->assignLead(Lead::factory()->contacted()->recycle($location)->create(), $otherAgent);

        Livewire::actingAs($agent)
            ->test(ListLeads::class, [
                'activeTab' => 'working',
                'tableFilters' => [
                    'agents' => [
                        'value' => $agent->id,
                    ],
                ],
            ])
            ->assertCanSeeTableRecords([$contacted, $qualified])
            ->assertCanNotSeeTableRecords([$new, $lost, $otherContacted]);
    }

    public function test_lost_tab_shows_lost_leads_for_the_selected_agent(): void
    {
        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $otherAgent = User::factory()->for($location)->create();
        $lost = $this->assignLead(Lead::factory()->lost()->recycle($location)->create(), $agent);
        $contacted = $this->assignLead(Lead::factory()->contacted()->recycle($location)->create(), $agent);
        $otherLost = $this->assignLead(Lead::factory()->lost()->recycle($location)->create(), $otherAgent);

        Livewire::actingAs($agent)
            ->test(ListLeads::class, [
                'activeTab' => 'lost',
                'tableFilters' => [
                    'agents' => [
                        'value' => $agent->id,
                    ],
                ],
            ])
            ->assertCanSeeTableRecords([$lost])
            ->assertCanNotSeeTableRecords([$contacted, $otherLost]);
    }

    public function test_closed_tab_shows_leads_with_a_closed_sale_for_the_selected_agent(): void
    {
        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $otherAgent = User::factory()->for($location)->create();
        $closed = $this->assignLead(Lead::factory()->qualified()->recycle($location)->create(), $agent);
        $pending = $this->assignLead(Lead::factory()->qualified()->recycle($location)->create(), $agent);
        $otherClosed = $this->assignLead(Lead::factory()->qualified()->recycle($location)->create(), $otherAgent);
        $closedStatus = SaleStatus::factory()->create([
            'name' => 'Closed',
            'slug' => 'closed',
        ]);
        $pendingStatus = SaleStatus::factory()->create([
            'name' => 'Pending',
            'slug' => 'pending',
        ]);
        Sale::factory()->closed()->recycle([$closedStatus, $location])->for($closed)->create();
        Sale::factory()->pending()->recycle([$pendingStatus, $location])->for($pending)->create();
        Sale::factory()->closed()->recycle([$closedStatus, $location])->for($otherClosed)->create();

        Livewire::actingAs($agent)
            ->test(ListLeads::class, [
                'activeTab' => 'closed',
                'tableFilters' => [
                    'agents' => [
                        'value' => $agent->id,
                    ],
                ],
            ])
            ->assertCanSeeTableRecords([$closed])
            ->assertCanNotSeeTableRecords([$pending, $otherClosed]);
    }

    public function test_create_related_lead_prefills_contact_fields_and_opposite_type(): void
    {
        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $listingAgent = User::factory()->for($location)->create();
        $source = LeadSource::factory()->create();
        $lead = Lead::factory()->recycle($location)->create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'phone' => '555-0100',
            'type' => SaleType::Seller,
            'lead_source_id' => $source->id,
        ]);
        $lead->agents()->attach([$listingAgent->id]);

        Livewire::actingAs($agent)
            ->test(CreateLead::class, ['related' => $lead->id])
            ->assertFormSet([
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'email' => 'jane@example.com',
                'phone' => '555-0100',
                'lead_source_id' => $source->id,
                'type' => SaleType::Buyer,
                'agents' => [$listingAgent->id],
            ]);
    }

    public function test_edit_page_links_to_create_related_lead(): void
    {
        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $lead = Lead::factory()->recycle($location)->create();

        Livewire::actingAs($agent)
            ->test(EditLead::class, ['record' => $lead->getRouteKey()])
            ->assertActionExists('createRelatedLead');
    }

    private function assignLead(Lead $lead, User $agent): Lead
    {
        $lead->agents()->attach($agent);

        return $lead;
    }
}
