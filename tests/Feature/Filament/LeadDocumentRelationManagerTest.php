<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Leads\Pages\EditLead;
use App\Filament\Resources\Leads\RelationManagers\DocumentsRelationManager;
use App\Models\Lead;
use App\Models\LeadDocument;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LeadDocumentRelationManagerTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: bool}>
     */
    public static function officeRoles(): array
    {
        return [
            'agent' => [false],
            'admin' => [true],
        ];
    }

    #[DataProvider('officeRoles')]
    public function test_office_member_can_attach_a_pdf(bool $isAdmin): void
    {
        Storage::fake('local');

        $location = Location::factory()->create();
        $members = User::factory()->for($location);

        $member = $isAdmin ? $members->admin()->create() : $members->create();
        $otherMember = User::factory()->for($location)->create();
        $lead = Lead::factory()->recycle($location)->create();
        $file = UploadedFile::fake()->createWithContent('pre-approval.pdf', "%PDF-1.4\n%%EOF");

        Livewire::actingAs($member)
            ->test(DocumentsRelationManager::class, [
                'ownerRecord' => $lead,
                'pageClass' => EditLead::class,
            ])
            ->callTableAction('create', data: [
                'path' => $file,
                'user_id' => $otherMember->id,
            ])
            ->assertHasNoTableActionErrors();

        $document = $lead->documents()->first();

        $this->assertNotNull($document);
        $this->assertSame($member->id, $document->user_id);
        $this->assertSame('pre-approval.pdf', $document->original_name);
        $this->assertSame('application/pdf', $document->mime_type);
        $this->assertSame('local', $document->disk);
        Storage::disk('local')->assertExists($document->path);
    }

    public function test_attached_pdf_name_links_to_a_new_tab(): void
    {
        Storage::fake('local');

        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $lead = Lead::factory()->recycle($location)->create();
        $path = 'lead-documents/'.$lead->id.'/pre-approval.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4');
        $document = LeadDocument::factory()->for($lead)->for($agent)->create([
            'path' => $path,
            'original_name' => 'pre-approval.pdf',
            'mime_type' => 'application/pdf',
            'size' => 8,
        ]);

        Livewire::actingAs($agent)
            ->test(DocumentsRelationManager::class, [
                'ownerRecord' => $lead,
                'pageClass' => EditLead::class,
            ])
            ->assertSee('pre-approval.pdf')
            ->assertSee('PDF')
            ->assertSeeHtml('target="_blank"')
            ->assertSeeHtml(e($document->viewUrl()));
    }

    public function test_document_requires_a_file(): void
    {
        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $lead = Lead::factory()->recycle($location)->create();

        Livewire::actingAs($agent)
            ->test(DocumentsRelationManager::class, [
                'ownerRecord' => $lead,
                'pageClass' => EditLead::class,
            ])
            ->callTableAction('create', data: [
                'path' => null,
            ])
            ->assertHasTableActionErrors([
                'path' => 'required',
            ]);

        $this->assertSame(0, $lead->documents()->count());
    }

    public function test_text_file_is_rejected(): void
    {
        Storage::fake('local');

        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $lead = Lead::factory()->recycle($location)->create();

        Livewire::actingAs($agent)
            ->test(DocumentsRelationManager::class, [
                'ownerRecord' => $lead,
                'pageClass' => EditLead::class,
            ])
            ->callTableAction('create', data: [
                'path' => UploadedFile::fake()->createWithContent('notes.txt', 'not a document'),
            ])
            ->assertHasTableActionErrors(['path']);

        $this->assertSame(0, $lead->documents()->count());
    }

    public function test_text_bytes_named_as_a_pdf_are_rejected(): void
    {
        Storage::fake('local');

        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $lead = Lead::factory()->recycle($location)->create();

        Livewire::actingAs($agent)
            ->test(DocumentsRelationManager::class, [
                'ownerRecord' => $lead,
                'pageClass' => EditLead::class,
            ])
            ->callTableAction('create', data: [
                'path' => UploadedFile::fake()->createWithContent('notes.pdf', 'not a document'),
            ])
            ->assertHasTableActionErrors([
                'path' => function (array $rules, array $messages): bool {
                    return str_contains(
                        implode(' ', $messages),
                        'The document must be a PDF, image, Word, or Excel file.',
                    );
                },
            ]);

        $this->assertSame(0, $lead->documents()->count());
    }

    public function test_double_extension_is_stored_as_the_detected_type(): void
    {
        Storage::fake('local');

        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $lead = Lead::factory()->recycle($location)->create();

        Livewire::actingAs($agent)
            ->test(DocumentsRelationManager::class, [
                'ownerRecord' => $lead,
                'pageClass' => EditLead::class,
            ])
            ->callTableAction('create', data: [
                'path' => UploadedFile::fake()
                    ->createWithContent('invoice.pdf.exe', "%PDF-1.4\n%%EOF")
                    ->mimeType('application/pdf'),
            ])
            ->assertHasNoTableActionErrors();

        $document = $lead->documents()->first();

        $this->assertNotNull($document);
        $this->assertSame('invoice.pdf', $document->original_name);
        $this->assertSame('application/pdf', $document->mime_type);
    }

    public function test_agent_can_delete_a_document_uploaded_by_a_coworker(): void
    {
        Storage::fake('local');

        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $coworker = User::factory()->for($location)->create();
        $lead = Lead::factory()->recycle($location)->create();
        $path = 'lead-documents/'.$lead->id.'/pre-approval.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4');
        $document = LeadDocument::factory()->for($lead)->for($coworker)->create([
            'path' => $path,
            'original_name' => 'pre-approval.pdf',
            'mime_type' => 'application/pdf',
            'size' => 8,
        ]);

        Livewire::actingAs($agent)
            ->test(DocumentsRelationManager::class, [
                'ownerRecord' => $lead,
                'pageClass' => EditLead::class,
            ])
            ->callTableAction('delete', $document)
            ->assertHasNoTableActionErrors();

        Storage::disk('local')->assertMissing($path);
        $this->assertModelMissing($document);
    }

    public function test_agent_cannot_open_documents_for_a_lead_at_another_office(): void
    {
        $agent = User::factory()->create();
        $lead = Lead::factory()->create();

        Livewire::actingAs($agent)
            ->test(DocumentsRelationManager::class, [
                'ownerRecord' => $lead,
                'pageClass' => EditLead::class,
            ])
            ->assertForbidden();

        $this->assertSame(0, LeadDocument::query()->count());
    }
}
