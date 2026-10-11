<?php

namespace App\Models;

use App\Models\Concerns\StoresOfficeDocument;
use Database\Factories\LeadDocumentFactory;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'lead_id',
    'user_id',
    'disk',
    'path',
    'original_name',
    'mime_type',
    'size',
])]
class LeadDocument extends Model
{
    /** @use HasFactory<LeadDocumentFactory> */
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
        return route(Filament::getDefaultPanel()->generateRouteName('leads.documents.show'), [
            'lead' => $this->lead_id,
            'document' => $this,
        ], absolute: false);
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
