<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadDocument;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LeadDocumentControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_from_a_lead_document(): void
    {
        $document = LeadDocument::factory()->create();

        $this->get($document->viewUrl())
            ->assertRedirect(route('filament.app.auth.login'));
    }

    public function test_unassigned_agent_opens_a_pdf_inline_without_a_sandbox_header(): void
    {
        Storage::fake('local');

        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $lead = Lead::factory()->recycle($location)->create();
        $document = $this->storeDocument($lead, $agent, 'application/pdf', 'pre-approval.pdf', '%PDF-1.4');

        $response = $this->actingAs($agent)->get($document->viewUrl());

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('inline;', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('pre-approval.pdf', (string) $response->headers->get('content-disposition'));
        $response->assertHeaderMissing('content-security-policy');
    }

    public function test_admin_downloads_a_non_pdf_document(): void
    {
        Storage::fake('local');

        $location = Location::factory()->create();
        $admin = User::factory()->for($location)->admin()->create();
        $lead = Lead::factory()->recycle($location)->create();
        $document = $this->storeDocument($lead, $admin, 'image/jpeg', 'photo.jpg', 'image-bytes');

        $this->actingAs($admin)
            ->get($document->viewUrl())
            ->assertDownload('photo.jpg');
    }

    public function test_agent_at_another_office_cannot_open_a_lead_document(): void
    {
        Storage::fake('local');

        $location = Location::factory()->create();
        $agent = User::factory()->create();
        $coworker = User::factory()->for($location)->create();
        $lead = Lead::factory()->recycle($location)->create();
        $document = $this->storeDocument($lead, $coworker, 'application/pdf', 'pre-approval.pdf', '%PDF-1.4');

        $this->actingAs($agent)
            ->get($document->viewUrl())
            ->assertNotFound();
    }

    public function test_document_from_another_lead_is_not_found(): void
    {
        Storage::fake('local');

        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $lead = Lead::factory()->recycle($location)->create();
        $otherLead = Lead::factory()->recycle($location)->create();
        $otherDocument = $this->storeDocument($otherLead, $agent, 'application/pdf', 'pre-approval.pdf', '%PDF-1.4');

        $this->actingAs($agent)
            ->get(route('filament.app.leads.documents.show', [
                'lead' => $lead,
                'document' => $otherDocument,
            ]))
            ->assertNotFound();
    }

    public function test_missing_file_is_not_found(): void
    {
        Storage::fake('local');

        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $lead = Lead::factory()->recycle($location)->create();
        $document = LeadDocument::factory()->for($lead)->for($agent)->create([
            'path' => 'lead-documents/'.$lead->id.'/missing.pdf',
            'original_name' => 'missing.pdf',
            'mime_type' => 'application/pdf',
        ]);

        $this->actingAs($agent)
            ->get($document->viewUrl())
            ->assertNotFound();
    }

    private function storeDocument(Lead $lead, User $user, string $mimeType, string $name, string $contents): LeadDocument
    {
        $path = 'lead-documents/'.$lead->id.'/'.$name;

        Storage::disk('local')->put($path, $contents);

        return LeadDocument::factory()->for($lead)->for($user)->create([
            'path' => $path,
            'original_name' => $name,
            'mime_type' => $mimeType,
            'size' => strlen($contents),
        ]);
    }
}
