<?php

namespace App\Filament\Resources\SaleStatuses;

use App\Filament\Concerns\ManagesLookupRecords;
use App\Filament\Resources\SaleStatuses\Pages\ManageSaleStatuses;
use App\Models\SaleStatus;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class SaleStatusResource extends Resource
{
    use ManagesLookupRecords;

    protected static ?string $model = SaleStatus::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getPages(): array
    {
        return [
            'index' => ManageSaleStatuses::route('/'),
        ];
    }
}
