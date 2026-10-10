<?php

namespace App\Filament\Widgets;

use App\Enums\LeaderboardPeriod;
use App\Filament\Widgets\Concerns\AppliesAnalyticsLocationFilter;
use App\Models\Sale;
use App\Models\SaleUser;
use App\Models\User;
use Carbon\CarbonInterface;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LeaderboardTable extends TableWidget
{
    use AppliesAnalyticsLocationFilter;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    public LeaderboardPeriod $period = LeaderboardPeriod::YearToDate;

    public static function canView(): bool
    {
        return auth()->user() instanceof User;
    }

    /**
     * @return int | string | array<string, int | null>
     */
    public function getColumnSpan(): int|string|array
    {
        if ($this->period === LeaderboardPeriod::YearToDate) {
            return 'full';
        }

        return 1;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading($this->period->label())
            ->description($this->period->description())
            ->query($this->agentsQuery())
            ->columns([
                TextColumn::make('rank')
                    ->label('#')
                    ->alignCenter()
                    ->grow(false)
                    ->badge()
                    ->icon(fn (int $state): ?Heroicon => $state === 1 ? Heroicon::Trophy : null)
                    ->color(fn (int $state): string|array => $this->rankColor($state))
                    ->getStateUsing(fn (User $record): int => $this->rankFor($record)),
                TextColumn::make('name')
                    ->weight(fn (User $record): FontWeight => match ($this->rankFor($record)) {
                        1 => FontWeight::Bold,
                        2, 3 => FontWeight::SemiBold,
                        default => FontWeight::Medium,
                    }),
                TextColumn::make('location.name')
                    ->label('Location')
                    ->placeholder('All locations')
                    ->color('gray')
                    ->visible(fn (): bool => $this->showsEveryOffice()),
                TextColumn::make('closed_volume')
                    ->label('Volume')
                    ->money('USD')
                    ->alignEnd()
                    ->grow(false)
                    ->weight(FontWeight::SemiBold)
                    ->color('primary'),
            ])
            ->defaultKeySort(false)
            ->paginated(false)
            ->emptyStateHeading('No closed sales')
            ->emptyStateDescription('Closed sales in this period will show up here.')
            ->emptyStateIcon(Heroicon::OutlinedTrophy);
    }

    /**
     * @return Builder<User>
     */
    private function agentsQuery(): Builder
    {
        $viewer = auth()->user();

        if (! $viewer instanceof User) {
            return User::query()->whereRaw('0 = 1');
        }

        [$start, $end] = $this->period->range();
        $locationId = $viewer->is_super_admin
            ? $this->selectedLocationId()
            : $viewer->location_id;

        $agents = User::query()->with('location');

        if ($viewer->is_super_admin) {
            $agents->inLocation($locationId);
        } else {
            $agents->atLocation($viewer->location_id);
        }

        return $agents
            ->whereHas(
                'sales',
                fn (Builder $sales): Builder => $sales
                    ->closedBetween($start, $end)
                    ->inLocation($locationId)
                    ->where('sale_user.commission_percent', '>', 0),
            )
            ->addSelect([
                'closed_volume' => $this->volumeQuery($start, $end, $locationId),
            ])
            ->withCasts([
                'closed_volume' => 'decimal:2',
            ])
            ->orderByDesc('closed_volume')
            ->orderBy('name')
            ->limit(10);
    }

    /**
     * @return Builder<SaleUser>
     */
    private function volumeQuery(CarbonInterface $start, CarbonInterface $end, ?int $locationId): Builder
    {
        return SaleUser::query()
            ->selectRaw('coalesce(sum('.SaleUser::priceShareExpression().'), 0)')
            ->join('sales', 'sales.id', '=', 'sale_user.sale_id')
            ->whereColumn('sale_user.user_id', 'users.id')
            ->where('sale_user.commission_percent', '>', 0)
            ->whereIn(
                'sale_user.sale_id',
                Sale::query()
                    ->closedBetween($start, $end)
                    ->inLocation($locationId)
                    ->select('sales.id'),
            );
    }

    /**
     * @return string | array<int, string>
     */
    private function rankColor(int $rank): string|array
    {
        $hex = match ($rank) {
            1 => '#e0aa16',
            2 => '#8d97a8',
            3 => '#c47b45',
            default => null,
        };

        if ($hex === null) {
            return 'gray';
        }

        return self::medalPalette($hex);
    }

    /**
     * @return array<int, string>
     */
    private static function medalPalette(string $hex): array
    {
        static $palettes = [];

        return $palettes[$hex] ??= Color::hex($hex);
    }

    private function showsEveryOffice(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->is_super_admin
            && $this->selectedLocationId() === null;
    }

    private function rankFor(User $record): int
    {
        $records = $this->getTableRecords();

        if ($records instanceof Paginator || $records instanceof CursorPaginator) {
            $records = $records->getCollection();
        }

        if (! $records instanceof Collection) {
            return 0;
        }

        $position = $records->values()->search(
            fn (User $ranked): bool => $ranked->is($record),
        );

        if ($position === false) {
            return 0;
        }

        return $position + 1;
    }
}
