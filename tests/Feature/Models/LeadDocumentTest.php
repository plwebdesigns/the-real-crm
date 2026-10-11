<?php

namespace Tests\Feature\Models;

use App\Models\Lead;
use App\Models\LeadDocument;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LeadDocumentTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_deleting_a_lead_removes_its_documents_from_storage(): void
    {
        Storage::fake('local');

        $lead = Lead::factory()->create();
        $this->storeDocument($lead, 'lead-documents/'.$lead->id.'/pre-approval.pdf');

        $lead->delete();

        Storage::disk('local')->assertMissing('lead-documents/'.$lead->id.'/pre-approval.pdf');
        $this->assertSame(0, LeadDocument::query()->count());
    }

    private function storeDocument(Lead $lead, string $path): LeadDocument
    {
        Storage::disk('local')->put($path, '%PDF-1.4');

        return LeadDocument::factory()->for($lead)->create([
            'path' => $path,
            'original_name' => 'pre-approval.pdf',
            'mime_type' => 'application/pdf',
            'size' => 8,
        ]);
    }
}
