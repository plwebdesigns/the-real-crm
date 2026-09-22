<?php

namespace App\Filament\Resources\Users\Concerns;

use App\Models\User;

trait ConstrainsUserLocationFields
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function constrainUserLocationFields(array $data): array
    {
        $actor = auth()->user();

        if (! $actor instanceof User) {
            return $data;
        }

        if ($actor->is_super_admin) {
            if (($data['is_super_admin'] ?? false) === true) {
                $data['is_admin'] = true;
                $data['location_id'] = null;
            }

            return $data;
        }

        $data['location_id'] = $actor->location_id;
        $data['is_super_admin'] = false;

        return $data;
    }
}
