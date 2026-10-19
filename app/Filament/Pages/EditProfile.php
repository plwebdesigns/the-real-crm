<?php

namespace App\Filament\Pages;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class EditProfile extends BaseEditProfile
{
    protected static bool $isDiscovered = false;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                Toggle::make('receives_task_reminder_emails')
                    ->label('Email me when a follow-up is due'),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getCurrentPasswordFormComponent(),
            ]);
    }
}
