<?php

namespace App\Filament\Resources\Sales\RelationManagers;

use App\Models\Sale;
use App\Models\SaleDocument;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

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
        if (! $ownerRecord instanceof Sale) {
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
                FileUpload::make('path')
                    ->label('Document')
                    ->disk('local')
                    ->directory(fn (): string => 'sale-documents/'.$this->getOwnerRecord()->getKey())
                    ->visibility('private')
                    ->acceptedFileTypes(SaleDocument::ACCEPTED_MIME_TYPES)
                    ->maxSize(SaleDocument::MAX_SIZE_KILOBYTES)
                    ->storeFileNamesIn('original_name')
                    ->preventFilePathTampering()
                    ->required()
                    ->helperText('PDF, images, Word, and Excel up to 20 MB.'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('user'))
            ->columns([
                TextColumn::make('original_name')
                    ->label('Document')
                    ->url(fn (SaleDocument $record): string => $record->viewUrl())
                    ->openUrlInNewTab(),
                TextColumn::make('mime_type')
                    ->label('Type')
                    ->formatStateUsing(fn (SaleDocument $record): string => $record->typeLabel()),
                TextColumn::make('user.name')
                    ->label('Uploaded by'),
                TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No documents')
            ->emptyStateDescription('Attach a PDF, image, Word, or Excel file.')
            ->headerActions([
                CreateAction::make()
                    ->label('Attach document')
                    ->modalHeading('Attach document')
                    ->mutateDataUsing(function (array $data): array {
                        $data['user_id'] = auth()->id();
                        $data['disk'] = 'local';

                        return $data;
                    }),
            ])
            ->recordActions([
                DeleteAction::make(),
            ]);
    }

    protected function getCreateAuthorizationResponse(): Response
    {
        return $this->saleUpdateResponse() ?? parent::getCreateAuthorizationResponse();
    }

    protected function getDeleteAuthorizationResponse(Model $record): Response
    {
        return $this->saleUpdateResponse() ?? parent::getDeleteAuthorizationResponse($record);
    }

    private function saleUpdateResponse(): ?Response
    {
        $ownerRecord = $this->getOwnerRecord();

        if (! $ownerRecord instanceof Sale) {
            return Response::deny();
        }

        $user = auth()->user();

        if (! $user instanceof User || ! $user->can('update', $ownerRecord)) {
            return Response::deny();
        }

        return null;
    }
}
