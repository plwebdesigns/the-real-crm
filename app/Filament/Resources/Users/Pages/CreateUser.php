<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\Concerns\ConstrainsUserLocationFields;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

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
        $data = $this->constrainUserLocationFields($data);
        $data['password'] = Str::password();

        return $data;
    }

    protected function afterCreate(): void
    {
        $user = $this->getRecord();

        if ($user instanceof User) {
            $user->sendEmailVerificationNotification();
        }
    }
}
