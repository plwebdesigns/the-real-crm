<?php

namespace App\Filament\Resources\Sales\Pages;

use App\Filament\Resources\Sales\SaleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListSales extends ListRecords
{
    protected static string $resource = SaleResource::class;

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
            'closed' => Tab::make()
                ->modifyQueryUsing(
                    fn (Builder $query): Builder => $query->closedInYear(now()->year),
                ),
            'pending' => Tab::make()
                ->modifyQueryUsing(
                    fn (Builder $query): Builder => $query->pending(),
                ),
        ];
    }
}
