<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Filament\Resources\Leads\LeadResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListLeads extends ListRecords
{
    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make(),
            'working' => Tab::make()
                ->modifyQueryUsing(
                    fn (Builder $query): Builder => $query->working(),
                ),
            'lost' => Tab::make()
                ->modifyQueryUsing(
                    fn (Builder $query): Builder => $query->lost(),
                ),
            'closed' => Tab::make()
                ->modifyQueryUsing(
                    fn (Builder $query): Builder => $query->closed(),
                ),
        ];
    }
}
