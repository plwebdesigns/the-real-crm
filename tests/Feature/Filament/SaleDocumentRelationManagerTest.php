<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Sales\Pages\EditSale;
use App\Filament\Resources\Sales\RelationManagers\DocumentsRelationManager;
use App\Models\Location;
use App\Models\Sale;
use App\Models\SaleDocument;
use App\Models\SaleUser;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SaleDocumentRelationManagerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_assigned_agent_can_attach_a_pdf(): void
    {
        Storage::fake('local');

        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $sale = $this->assignAgent(
            Sale::factory()->withoutAgents()->recycle($location)->create(),
            $agent,
        );
        $file = UploadedFile::fake()->createWithContent('contract.pdf', "%PDF-1.4\n%%EOF");

        Livewire::actingAs($agent)
            ->test(DocumentsRelationManager::class, [
                'ownerRecord' => $sale,
                'pageClass' => EditSale::class,
            ])
            ->callTableAction('create', data: [
                'path' => $file,
            ])
            ->assertHasNoTableActionErrors();

        $document = $sale->documents()->first();

        $this->assertNotNull($document);
        $this->assertSame($agent->id, $document->user_id);
        $this->assertSame('contract.pdf', $document->original_name);
        $this->assertSame('application/pdf', $document->mime_type);
        $this->assertSame('local', $document->disk);
        Storage::disk('local')->assertExists($document->path);
    }

    public function test_attached_pdf_name_links_to_a_new_tab(): void
    {
        Storage::fake('local');

        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $sale = $this->assignAgent(
            Sale::factory()->withoutAgents()->recycle($location)->create(),
            $agent,
        );
        $path = 'sale-documents/'.$sale->id.'/contract.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4');
        $document = SaleDocument::factory()->for($sale)->for($agent)->create([
            'path' => $path,
            'original_name' => 'contract.pdf',
            'mime_type' => 'application/pdf',
            'size' => 8,
        ]);

        Livewire::actingAs($agent)
            ->test(DocumentsRelationManager::class, [
                'ownerRecord' => $sale,
                'pageClass' => EditSale::class,
            ])
            ->assertSee('contract.pdf')
            ->assertSee('PDF')
            ->assertSeeHtml('target="_blank"')
            ->assertSeeHtml(e($document->viewUrl()));
    }

    public function test_document_requires_a_file(): void
    {
        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $sale = $this->assignAgent(
            Sale::factory()->withoutAgents()->recycle($location)->create(),
            $agent,
        );

        Livewire::actingAs($agent)
            ->test(DocumentsRelationManager::class, [
                'ownerRecord' => $sale,
                'pageClass' => EditSale::class,
            ])
            ->callTableAction('create', data: [
                'path' => null,
            ])
            ->assertHasTableActionErrors([
                'path' => 'required',
            ]);

        $this->assertSame(0, $sale->documents()->count());
    }

    public function test_text_file_is_rejected(): void
    {
        Storage::fake('local');

        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $sale = $this->assignAgent(
            Sale::factory()->withoutAgents()->recycle($location)->create(),
            $agent,
        );

        Livewire::actingAs($agent)
            ->test(DocumentsRelationManager::class, [
                'ownerRecord' => $sale,
                'pageClass' => EditSale::class,
            ])
            ->callTableAction('create', data: [
                'path' => UploadedFile::fake()->createWithContent('notes.txt', 'not a contract'),
            ])
            ->assertHasTableActionErrors([
                'path' => function (array $rules, array $messages): bool {
                    return str_contains(
                        implode(' ', $messages),
                        'must be a file of type: application/pdf, image/jpeg, image/png, image/webp, application/msword, application/vnd.openxmlformats-officedocument.wordprocessingml.document, application/vnd.ms-excel, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.',
                    );
                },
            ]);

        $this->assertSame(0, $sale->documents()->count());
    }

    public function test_agent_can_delete_an_attached_document(): void
    {
        Storage::fake('local');

        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $sale = $this->assignAgent(
            Sale::factory()->withoutAgents()->recycle($location)->create(),
            $agent,
        );
        $path = 'sale-documents/'.$sale->id.'/contract.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4');
        $document = SaleDocument::factory()->for($sale)->for($agent)->create([
            'path' => $path,
            'original_name' => 'contract.pdf',
            'mime_type' => 'application/pdf',
            'size' => 8,
        ]);

        Livewire::actingAs($agent)
            ->test(DocumentsRelationManager::class, [
                'ownerRecord' => $sale,
                'pageClass' => EditSale::class,
            ])
            ->callTableAction('delete', $document)
            ->assertHasNoTableActionErrors();

        Storage::disk('local')->assertMissing($path);
        $this->assertModelMissing($document);
    }

    public function test_agent_cannot_open_documents_for_another_agents_sale(): void
    {
        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $coworker = User::factory()->for($location)->create();
        $sale = $this->assignAgent(
            Sale::factory()->withoutAgents()->recycle($location)->create(),
            $coworker,
        );

        Livewire::actingAs($agent)
            ->test(DocumentsRelationManager::class, [
                'ownerRecord' => $sale,
                'pageClass' => EditSale::class,
            ])
            ->assertForbidden();

        $this->assertSame(0, SaleDocument::query()->count());
    }

    private function assignAgent(Sale $sale, User $agent): Sale
    {
        $sale->agents()->attach($agent, [
            'commission_percent' => 100,
            'net_commission' => SaleUser::netCommissionFor(
                Sale::remainingCommissionFor($sale->gross_commission, $sale->brokerage_fee),
                100,
            ),
        ]);

        return $sale;
    }
}
