<?php

namespace App\Filament\Widgets\Concerns;

use App\Models\User;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

trait AppliesAnalyticsLocationFilter
{
    use InteractsWithPageFilters;

    protected function selectedLocationId(): ?int
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->is_super_admin) {
            return null;
        }

        $locationId = $this->pageFilters['location_id'] ?? null;

        if (! is_numeric($locationId)) {
            return null;
        }

        return (int) $locationId;
    }

    /**
     * @param  array<string, array<string, mixed>>  $filters
     * @return array<string, array<string, mixed>>
     */
    protected function filtersIncludingLocation(array $filters = []): array
    {
        $locationId = $this->selectedLocationId();

        if ($locationId === null) {
            return $filters;
        }

        $filters['location_id'] = [
            'value' => $locationId,
        ];

        return $filters;
    }
}
