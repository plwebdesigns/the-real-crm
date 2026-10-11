<?php

namespace App\Models\Concerns;

use Closure;
use finfo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait StoresOfficeDocument
{
    public const MAX_SIZE_KILOBYTES = 20480;

    /**
     * @var list<string>
     */
    public const ACCEPTED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    /**
     * The first extension is used when the uploaded name does not already end in one of these.
     *
     * @var array<string, list<string>>
     */
    private const EXTENSIONS_BY_MIME_TYPE = [
        'application/pdf' => ['pdf'],
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
        'application/msword' => ['doc'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
        'application/vnd.ms-excel' => ['xls'],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
    ];

    protected static function bootStoresOfficeDocument(): void
    {
        static::saving(function (Model $document): void {
            static::guardStoredFile($document);
        });

        static::deleted(function (Model $document): void {
            if (blank($document->getAttribute('path'))) {
                return;
            }

            $disk = $document->getAttribute('disk');

            Storage::disk(is_string($disk) && $disk !== '' ? $disk : 'local')
                ->delete($document->getAttribute('path'));
        });
    }

    /**
     * @return list<string>
     */
    public static function temporaryUploadRules(): array
    {
        return [
            'required',
            'file',
            'mimetypes:'.implode(',', self::ACCEPTED_MIME_TYPES),
            'max:'.self::MAX_SIZE_KILOBYTES,
        ];
    }

    public static function contentRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! $value instanceof File) {
                return;
            }

            $mimeType = self::sniffedMimeType($value->getPathname());

            if (! is_string($mimeType) || ! in_array($mimeType, self::ACCEPTED_MIME_TYPES, true)) {
                $fail('The document must be a PDF, image, Word, or Excel file.');
            }
        };
    }

    public function typeLabel(): string
    {
        return match ($this->mime_type) {
            'application/pdf' => 'PDF',
            'image/jpeg', 'image/png', 'image/webp' => 'Image',
            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'Word',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'Excel',
            default => 'File',
        };
    }

    public function fileResponse(): StreamedResponse
    {
        $diskName = $this->getAttribute('disk');
        $disk = Storage::disk(is_string($diskName) && $diskName !== '' ? $diskName : 'local');
        $path = $this->getAttribute('path');

        abort_unless(is_string($path) && $path !== '' && $disk->exists($path), 404);

        $mimeType = self::sniffedMimeType($disk->path($path));

        abort_unless(is_string($mimeType) && in_array($mimeType, self::ACCEPTED_MIME_TYPES, true), 404);

        $downloadName = self::downloadName((string) $this->getAttribute('original_name'), $mimeType);
        $headers = [
            'Content-Type' => $mimeType,
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($mimeType === 'application/pdf') {
            $headers['Content-Security-Policy'] = 'sandbox';

            return $disk->response($path, $downloadName, $headers);
        }

        return $disk->download($path, $downloadName, $headers);
    }

    private static function guardStoredFile(Model $document): void
    {
        $diskName = $document->getAttribute('disk');
        $diskName = is_string($diskName) && $diskName !== '' ? $diskName : 'local';
        $document->setAttribute('disk', $diskName);

        $path = $document->getAttribute('path');

        if (! is_string($path) || $path === '' || ! Storage::disk($diskName)->exists($path)) {
            return;
        }

        $disk = Storage::disk($diskName);
        $mimeType = self::sniffedMimeType($disk->path($path));

        if (! is_string($mimeType) || ! in_array($mimeType, self::ACCEPTED_MIME_TYPES, true)) {
            $disk->delete($path);

            throw ValidationException::withMessages([
                'path' => 'The document must be a PDF, image, Word, or Excel file.',
            ]);
        }

        $document->setAttribute('mime_type', $mimeType);
        $document->setAttribute(
            'original_name',
            self::downloadName((string) $document->getAttribute('original_name'), $mimeType),
        );

        if ($document->getAttribute('size') === null) {
            $document->setAttribute('size', $disk->size($path));
        }
    }

    /**
     * Read the type from the bytes. Storage's MIME helper falls back to the filename,
     * so a text file named `notes.pdf` would otherwise be treated as a PDF.
     */
    private static function sniffedMimeType(string $absolutePath): ?string
    {
        if (! is_file($absolutePath)) {
            return null;
        }

        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($absolutePath);

        return is_string($mimeType) && $mimeType !== '' ? $mimeType : null;
    }

    /**
     * Drop trailing extensions until the name ends with one that matches the sniffed type.
     * `invoice.pdf.exe` is stored and downloaded as `invoice.pdf`.
     */
    private static function downloadName(string $originalName, string $mimeType): string
    {
        $extensions = self::EXTENSIONS_BY_MIME_TYPE[$mimeType] ?? ['bin'];
        $name = self::plainBasename($originalName);

        while ($name !== '') {
            if (preg_match('/^(.*)\.([A-Za-z0-9]+)$/', $name, $matches) !== 1) {
                break;
            }

            $extension = strtolower($matches[2]);

            if (in_array($extension, $extensions, true)) {
                $stem = trim($matches[1], ". \t");

                return ($stem === '' ? 'document' : Str::limit($stem, 250, '')).'.'.$extension;
            }

            $name = trim($matches[1], ". \t");
        }

        $stem = $name === '' ? 'document' : $name;

        return Str::limit($stem, 250, '').'.'.$extensions[0];
    }

    private static function plainBasename(string $originalName): string
    {
        $name = basename(str_replace('\\', '/', $originalName));
        $name = preg_replace('/[^\w.\- ()]+/u', '', $name) ?? '';

        return trim($name, ". \t");
    }
}
