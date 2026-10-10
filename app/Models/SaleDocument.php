<?php

namespace App\Models;

use Database\Factories\SaleDocumentFactory;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'sale_id',
    'user_id',
    'disk',
    'path',
    'original_name',
    'mime_type',
    'size',
])]
class SaleDocument extends Model
{
    /** @use HasFactory<SaleDocumentFactory> */
    use HasFactory;

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
     * @var array<string, mixed>
     */
    protected $attributes = [
        'disk' => 'local',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SaleDocument $document): void {
            $document->disk = $document->disk ?: 'local';

            if (blank($document->path) || ! Storage::disk($document->disk)->exists($document->path)) {
                return;
            }

            if (blank($document->mime_type)) {
                $mimeType = Storage::disk($document->disk)->mimeType($document->path);

                $document->mime_type = is_string($mimeType) && $mimeType !== ''
                    ? $mimeType
                    : 'application/octet-stream';
            }

            if ($document->size === null) {
                $document->size = Storage::disk($document->disk)->size($document->path);
            }
        });

        static::deleted(function (SaleDocument $document): void {
            if (blank($document->path)) {
                return;
            }

            Storage::disk($document->disk ?: 'local')->delete($document->path);
        });
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
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

    public function viewUrl(): string
    {
        return route(Filament::getDefaultPanel()->generateRouteName('sales.documents.show'), [
            'sale' => $this->sale_id,
            'document' => $this,
        ]);
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
