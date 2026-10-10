<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class CreatePassword extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'create-password';

    protected static ?string $title = 'Create password';

    protected static string $layout = 'filament-panels::components.layout.simple';

    protected string $view = 'filament-panels::pages.simple';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->hasVerifiedEmail()
            && ! $user->hasSetPassword();
    }

    public function hasLogo(): bool
    {
        return true;
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'Create your password';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Choose a password to finish setting up your account.';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                TextInput::make('password')
                    ->label('Password')
                    ->password()
                    ->autocomplete('new-password')
                    ->revealable()
                    ->required()
                    ->rule(Password::default())
                    ->same('passwordConfirmation')
                    ->validationAttribute('password'),
                TextInput::make('passwordConfirmation')
                    ->label('Confirm password')
                    ->password()
                    ->autocomplete('new-password')
                    ->revealable()
                    ->required()
                    ->dehydrated(false),
            ]);
    }

    public function createPassword(): void
    {
        $user = auth()->user();

        if (! $user instanceof User || $user->hasSetPassword()) {
            abort(403);
        }

        $data = $this->form->getState();

        $user->forceFill([
            'password' => $data['password'],
            'remember_token' => Str::random(60),
        ])->save();

        if (request()->hasSession()) {
            request()->session()->put([
                'password_hash_'.Filament::getAuthGuard() => $user->getAuthPassword(),
            ]);
        }

        $this->redirect(Filament::getUrl());
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('createPassword')
                    ->footer([
                        Actions::make([
                            Action::make('createPassword')
                                ->label('Create password')
                                ->submit('createPassword'),
                        ])
                            ->fullWidth(),
                    ]),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getLayoutData(): array
    {
        return [
            'hasTopbar' => true,
            'maxContentWidth' => $this->getMaxContentWidth(),
            'maxWidth' => $this->getMaxContentWidth(),
        ];
    }
}
