<?php

namespace App\Filament\Resources\Leads\RelationManagers;

use App\Filament\Resources\Tasks\Schemas\TaskForm;
use App\Filament\Resources\Tasks\Tables\TasksTable;
use App\Models\Lead;
use App\Models\Sale;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

class TasksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

    public function mount(): void
    {
        abort_unless(
            static::canViewForRecord($this->getOwnerRecord(), $this->pageClass ?? static::class),
            403,
        );

        parent::mount();
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if (! static::userCanUpdate($ownerRecord)) {
            return false;
        }

        return parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public function form(Schema $schema): Schema
    {
        return TaskForm::configure($schema, $this->getOwnerRecord());
    }

    public function table(Table $table): Table
    {
        $owner = $this->getOwnerRecord();

        return TasksTable::configure(
            $table,
            showLead: ! $owner instanceof Lead,
            showSale: ! $owner instanceof Sale,
        )->headerActions([
            CreateAction::make()
                ->mutateDataUsing(function (array $data): array {
                    $owner = $this->getOwnerRecord();

                    if ($owner instanceof Sale) {
                        $data['lead_id'] = $owner->lead_id;
                        $data['sale_id'] = $owner->getKey();
                    }

                    $data['user_id'] = auth()->id();

                    return $data;
                }),
        ]);
    }

    protected function getCreateAuthorizationResponse(): Response
    {
        if (! static::userCanUpdate($this->getOwnerRecord())) {
            return Response::deny();
        }

        return parent::getCreateAuthorizationResponse();
    }

    private static function userCanUpdate(Model $ownerRecord): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        if (! $ownerRecord instanceof Lead && ! $ownerRecord instanceof Sale) {
            return false;
        }

        return $user->can('update', $ownerRecord);
    }
}
