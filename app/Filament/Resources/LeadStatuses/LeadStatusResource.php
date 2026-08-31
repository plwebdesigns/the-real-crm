<?php

namespace App\Filament\Resources\LeadStatuses;

use App\Filament\Concerns\ManagesLookupRecords;
use App\Filament\Resources\LeadStatuses\Pages\ManageLeadStatuses;
use App\Models\LeadStatus;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class LeadStatusResource extends Resource
{
    use ManagesLookupRecords;

    protected static ?string $model = LeadStatus::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getPages(): array
    {
        return [
            'index' => ManageLeadStatuses::route('/'),
        ];
    }
}
