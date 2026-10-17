<?php

namespace App\Filament\Resources\Tasks;

use App\Filament\Concerns\ScopesRecordsToLocation;
use App\Filament\Resources\Tasks\Pages\CreateTask;
use App\Filament\Resources\Tasks\Pages\EditTask;
use App\Filament\Resources\Tasks\Pages\ListTasks;
use App\Filament\Resources\Tasks\Schemas\TaskForm;
use App\Filament\Resources\Tasks\Tables\TasksTable;
use App\Models\Task;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class TaskResource extends Resource
{
    use ScopesRecordsToLocation;

    protected static ?string $model = Task::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return TaskForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TasksTable::configure($table);
    }

    public static function getRecordTitle(?Model $record): ?string
    {
        if (! $record instanceof Task) {
            return null;
        }

        $type = ucfirst(strtolower((string) $record->type->getLabel()));
        $name = $record->lead?->full_name;

        return $name === null ? $type : "{$type} for {$name}";
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTasks::route('/'),
            'create' => CreateTask::route('/create'),
            'edit' => EditTask::route('/{record}/edit'),
        ];
    }
}
