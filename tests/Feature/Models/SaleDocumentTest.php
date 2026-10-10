<?php

namespace Tests\Feature\Models;

use App\Models\Sale;
use App\Models\SaleDocument;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SaleDocumentTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_deleting_a_sale_removes_its_documents_from_storage(): void
    {
        Storage::fake('local');

        $sale = Sale::factory()->create();
        $this->storeDocument($sale, 'sale-documents/'.$sale->id.'/contract.pdf');

        $sale->delete();

        Storage::disk('local')->assertMissing('sale-documents/'.$sale->id.'/contract.pdf');
        $this->assertSame(0, SaleDocument::query()->count());
    }

    private function storeDocument(Sale $sale, string $path): SaleDocument
    {
        Storage::disk('local')->put($path, '%PDF-1.4');

        return SaleDocument::factory()->for($sale)->create([
            'path' => $path,
            'original_name' => 'contract.pdf',
            'mime_type' => 'application/pdf',
            'size' => 8,
        ]);
    }
}
