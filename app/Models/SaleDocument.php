<?php

namespace App\Models;

use App\Models\Concerns\StoresOfficeDocument;
use Database\Factories\SaleDocumentFactory;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    use HasFactory, StoresOfficeDocument;

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
