<?php

namespace App\Filament\Resources\Tasks\Pages;

use App\Filament\Resources\Tasks\TaskResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListTasks extends ListRecords
{
    protected static string $resource = TaskResource::class;

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
            'open' => Tab::make()
                ->modifyQueryUsing(
                    fn (Builder $query): Builder => $query->open(),
                ),
            'completed' => Tab::make()
                ->modifyQueryUsing(
                    fn (Builder $query): Builder => $query->completed(),
                ),
            'all' => Tab::make(),
        ];
    }
}
