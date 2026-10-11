<?php

namespace Tests\Feature\Models;

use App\Models\Lead;
use App\Models\LeadDocument;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function originalNames(): array
    {
        return [
            'double extension' => ['invoice.pdf.exe', 'invoice.pdf', 'application/pdf', 'pdf'],
            'trailing executable after several extensions' => ['invoice.pdf.exe.bak', 'invoice.pdf', 'application/pdf', 'pdf'],
            'directory prefix' => ['../../secret.pdf', 'secret.pdf', 'application/pdf', 'pdf'],
            'header break' => ["invoice.pdf\r\n.exe", 'invoice.pdf', 'application/pdf', 'pdf'],
            'dotted stem' => ['my.report.pdf', 'my.report.pdf', 'application/pdf', 'pdf'],
            'missing extension' => ['report', 'report.pdf', 'application/pdf', 'pdf'],
            'empty name' => ['', 'document.pdf', 'application/pdf', 'pdf'],
            'uppercase extension' => ['Pre-Approval.PDF', 'Pre-Approval.pdf', 'application/pdf', 'pdf'],
            'long name' => [str_repeat('a', 300).'.pdf.exe', str_repeat('a', 250).'.pdf', 'application/pdf', 'pdf'],
            'accented name' => ['résumé.pdf', 'résumé.pdf', 'application/pdf', 'pdf'],
            'jpeg alias' => ['photo.JPG', 'photo.jpg', 'image/jpeg', 'jpeg'],
            'jpeg extension' => ['photo.jpeg', 'photo.jpeg', 'image/jpeg', 'jpeg'],
        ];
    }

    #[DataProvider('originalNames')]
    public function test_original_name_is_stored_with_the_detected_extension(
        string $originalName,
        string $expectedName,
        string $expectedMimeType,
        string $kind,
    ): void {
        Storage::fake('local');

        $lead = Lead::factory()->create();
        $path = 'lead-documents/'.$lead->id.'/upload';
        Storage::disk('local')->put($path, $kind === 'jpeg' ? self::jpegBytes() : '%PDF-1.4');

        $document = LeadDocument::factory()->for($lead)->create([
            'path' => $path,
            'original_name' => $originalName,
            'mime_type' => 'application/octet-stream',
            'size' => 8,
        ]);

        $this->assertSame($expectedName, $document->original_name);
        $this->assertSame($expectedMimeType, $document->mime_type);
    }

    public function test_text_bytes_labeled_as_a_pdf_are_rejected(): void
    {
        Storage::fake('local');

        $lead = Lead::factory()->create();
        $path = 'lead-documents/'.$lead->id.'/notes.pdf';
        Storage::disk('local')->put($path, 'not a document');

        try {
            LeadDocument::factory()->for($lead)->create([
                'path' => $path,
                'original_name' => 'notes.pdf',
                'mime_type' => 'application/pdf',
                'size' => 14,
            ]);
            $this->fail('A text file was stored.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'The document must be a PDF, image, Word, or Excel file.',
                $exception->validator->errors()->first('path'),
            );
        }

        $this->assertSame(0, LeadDocument::query()->count());
        Storage::disk('local')->assertMissing($path);
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

    private static function jpegBytes(): string
    {
        return (string) hex2bin('ffd8ffe000104a46494600010100000100010000ffd9');
    }
}
