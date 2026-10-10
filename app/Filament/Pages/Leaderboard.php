<?php

namespace App\Filament\Pages;

use App\Enums\LeaderboardPeriod;
use App\Filament\Widgets\LeaderboardTable;
use App\Models\Location;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;

class Leaderboard extends Dashboard
{
    use HasFiltersForm;

    protected static string $routePath = 'leaderboard';

    protected static ?string $title = 'Leaderboard';

    protected static ?int $navigationSort = 0;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    public static function canAccess(): bool
    {
        return auth()->user() instanceof User;
    }

    public function content(Schema $schema): Schema
    {
        $user = auth()->user();

        return $schema
            ->components([
                ...($user instanceof User && $user->is_super_admin ? [$this->getFiltersFormContentComponent()] : []),
                $this->getWidgetsContentComponent(),
            ]);
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('location_id')
                    ->label('Location')
                    ->options(fn (): array => Location::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->placeholder('All locations'),
            ]);
    }

    /**
     * @return array<class-string<Widget> | WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        return collect(LeaderboardPeriod::cases())
            ->filter(fn (LeaderboardPeriod $period): bool => $period->hasStarted())
            ->map(fn (LeaderboardPeriod $period): WidgetConfiguration => LeaderboardTable::make([
                'period' => $period,
            ]))
            ->all();
    }
}
