<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\GenerateSignedUploadUrl;
use Tests\TestCase;

class TemporaryFileUploadTest extends TestCase
{
    public function test_text_bytes_named_as_a_pdf_are_rejected(): void
    {
        Storage::fake('tmp-for-tests');

        $this->withHeader('Accept', 'application/json')
            ->post(app(GenerateSignedUploadUrl::class)->forLocal(), [
                'files' => [
                    $this->uploadedFile('notes.pdf', 'not a document'),
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'files.0' => 'must be a file of type: application/pdf, image/jpeg, image/png, image/webp, application/msword, application/vnd.openxmlformats-officedocument.wordprocessingml.document, application/vnd.ms-excel, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.',
            ]);

        $this->assertSame([], Storage::disk('tmp-for-tests')->allFiles());
    }

    public function test_pdf_bytes_are_stored(): void
    {
        Storage::fake('tmp-for-tests');

        $this->post(app(GenerateSignedUploadUrl::class)->forLocal(), [
            'files' => [
                $this->uploadedFile('pre-approval.pdf', "%PDF-1.4\n%%EOF"),
            ],
        ])->assertOk()
            ->assertJsonStructure(['paths']);

        $uploads = array_values(array_filter(
            Storage::disk('tmp-for-tests')->allFiles(),
            fn (string $path): bool => str_starts_with($path, 'livewire-tmp/') && ! str_ends_with($path, '.json'),
        ));

        $this->assertCount(1, $uploads);
    }

    private function uploadedFile(string $name, string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, null, null, true);
    }
}
