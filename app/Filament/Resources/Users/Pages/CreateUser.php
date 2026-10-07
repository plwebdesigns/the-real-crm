<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\Concerns\ConstrainsUserLocationFields;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    use ConstrainsUserLocationFields;

    protected static string $resource = UserResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->constrainUserLocationFields($data);
    }

    protected function afterCreate(): void
    {
        $user = $this->getRecord();

        if ($user instanceof User) {
            $user->sendEmailVerificationNotification();
        }
    }
}
