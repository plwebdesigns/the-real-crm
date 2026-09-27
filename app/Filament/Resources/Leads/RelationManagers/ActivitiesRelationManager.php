<?php

namespace App\Filament\Resources\Leads\RelationManagers;

use App\Enums\LeadActivityType;
use App\Models\Lead;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

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
        if (! $ownerRecord instanceof Lead) {
            return false;
        }

        $user = auth()->user();

        if (! $user instanceof User || ! $user->can('update', $ownerRecord)) {
            return false;
        }

        return parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->options(LeadActivityType::class)
                    ->required(),
                Textarea::make('body')
                    ->required()
                    ->rows(4),
                DateTimePicker::make('happened_at')
                    ->required()
                    ->default(now()),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('user'))
            ->columns([
                TextColumn::make('happened_at')
                    ->label('When')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('user.name')
                    ->label('User'),
                TextColumn::make('body')
                    ->limit(80)
                    ->wrap(),
            ])
            ->defaultSort('happened_at', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        $data['user_id'] = auth()->id();

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    protected function getCreateAuthorizationResponse(): Response
    {
        $ownerRecord = $this->getOwnerRecord();

        if (! $ownerRecord instanceof Lead) {
            return Response::deny();
        }

        $user = auth()->user();

        if (! $user instanceof User || ! $user->can('update', $ownerRecord)) {
            return Response::deny();
        }

        return parent::getCreateAuthorizationResponse();
    }
}
