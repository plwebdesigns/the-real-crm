<?php

namespace App\Filament\Resources\LeadStatuses\Pages;

use App\Filament\Resources\LeadStatuses\LeadStatusResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageLeadStatuses extends ManageRecords
{
    protected static string $resource = LeadStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
