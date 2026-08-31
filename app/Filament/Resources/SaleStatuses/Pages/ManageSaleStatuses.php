<?php

namespace App\Filament\Resources\SaleStatuses\Pages;

use App\Filament\Resources\SaleStatuses\SaleStatusResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSaleStatuses extends ManageRecords
{
    protected static string $resource = SaleStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
