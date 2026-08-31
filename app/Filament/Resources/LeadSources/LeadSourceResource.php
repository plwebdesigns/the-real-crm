<?php

namespace App\Filament\Resources\LeadSources;

use App\Filament\Concerns\ManagesLookupRecords;
use App\Filament\Resources\LeadSources\Pages\ManageLeadSources;
use App\Models\LeadSource;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class LeadSourceResource extends Resource
{
    use ManagesLookupRecords;

    protected static ?string $model = LeadSource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getPages(): array
    {
        return [
            'index' => ManageLeadSources::route('/'),
        ];
    }
}
