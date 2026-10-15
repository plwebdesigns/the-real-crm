<?php

namespace App\Filament\Resources\Tasks\Pages;

use App\Filament\Resources\Tasks\Tables\TasksTable;
use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Task;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTask extends EditRecord
{
    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TasksTable::completeAction()
                ->visible(fn (): bool => $this->getTask()?->isOpen() ?? false),
            TasksTable::reopenAction()
                ->visible(fn (): bool => $this->getTask() instanceof Task && ! $this->getTask()->isOpen()),
            DeleteAction::make(),
        ];
    }

    private function getTask(): ?Task
    {
        $record = $this->getRecord();

        return $record instanceof Task ? $record : null;
    }
}
