<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Filament\Resources\Leads\LeadResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLead extends EditRecord
{
    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createRelatedLead')
                ->label('Create related lead')
                ->url(fn (): string => LeadResource::getUrl('create', [
                    'related' => $this->getRecord()->id,
                ], isAbsolute: false)),
            DeleteAction::make(),
        ];
    }
}
