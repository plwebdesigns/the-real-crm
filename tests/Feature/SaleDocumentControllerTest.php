<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Sale;
use App\Models\SaleDocument;
use App\Models\SaleUser;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SaleDocumentControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_from_a_sale_document(): void
    {
        $document = SaleDocument::factory()->create();

        $this->get($document->viewUrl())
            ->assertRedirect(route('filament.app.auth.login'));
    }

    public function test_assigned_agent_opens_a_pdf_inline_without_a_sandbox_header(): void
    {
        Storage::fake('local');

        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $sale = $this->assignAgent(
            Sale::factory()->withoutAgents()->recycle($location)->create(),
            $agent,
        );
        $document = $this->storeDocument($sale, $agent, 'application/pdf', 'contract.pdf', '%PDF-1.4');

        $response = $this->actingAs($agent)->get($document->viewUrl());

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('inline;', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('contract.pdf', (string) $response->headers->get('content-disposition'));
        $response->assertHeaderMissing('content-security-policy');
    }

    public function test_assigned_agent_downloads_a_non_pdf_document(): void
    {
        Storage::fake('local');

        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $sale = $this->assignAgent(
            Sale::factory()->withoutAgents()->recycle($location)->create(),
            $agent,
        );
        $document = $this->storeDocument($sale, $agent, 'image/jpeg', 'photo.jpg', 'image-bytes');

        $this->actingAs($agent)
            ->get($document->viewUrl())
            ->assertDownload('photo.jpg');
    }

    public function test_agent_cannot_open_a_document_on_another_agents_sale(): void
    {
        Storage::fake('local');

        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $coworker = User::factory()->for($location)->create();
        $sale = $this->assignAgent(
            Sale::factory()->withoutAgents()->recycle($location)->create(),
            $coworker,
        );
        $document = $this->storeDocument($sale, $coworker, 'application/pdf', 'contract.pdf', '%PDF-1.4');

        $this->actingAs($agent)
            ->get($document->viewUrl())
            ->assertNotFound();
    }

    public function test_document_from_another_sale_is_not_found(): void
    {
        Storage::fake('local');

        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $sale = $this->assignAgent(
            Sale::factory()->withoutAgents()->recycle($location)->create(),
            $agent,
        );
        $otherSale = $this->assignAgent(
            Sale::factory()->withoutAgents()->recycle($location)->create(),
            $agent,
        );
        $otherDocument = $this->storeDocument($otherSale, $agent, 'application/pdf', 'contract.pdf', '%PDF-1.4');

        $this->actingAs($agent)
            ->get(route('filament.app.sales.documents.show', [
                'sale' => $sale,
                'document' => $otherDocument,
            ]))
            ->assertNotFound();
    }

    public function test_missing_file_is_not_found(): void
    {
        Storage::fake('local');

        $location = Location::factory()->create();
        $agent = User::factory()->for($location)->create();
        $sale = $this->assignAgent(
            Sale::factory()->withoutAgents()->recycle($location)->create(),
            $agent,
        );
        $document = SaleDocument::factory()->for($sale)->for($agent)->create([
            'path' => 'sale-documents/'.$sale->id.'/missing.pdf',
            'original_name' => 'missing.pdf',
            'mime_type' => 'application/pdf',
        ]);

        $this->actingAs($agent)
            ->get($document->viewUrl())
            ->assertNotFound();
    }

    private function storeDocument(Sale $sale, User $user, string $mimeType, string $name, string $contents): SaleDocument
    {
        $path = 'sale-documents/'.$sale->id.'/'.$name;

        Storage::disk('local')->put($path, $contents);

        return SaleDocument::factory()->for($sale)->for($user)->create([
            'path' => $path,
            'original_name' => $name,
            'mime_type' => $mimeType,
            'size' => strlen($contents),
        ]);
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
