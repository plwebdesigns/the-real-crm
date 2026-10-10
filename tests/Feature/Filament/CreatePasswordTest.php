<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\CreatePassword;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class CreatePasswordTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_from_create_password(): void
    {
        $this->get(CreatePassword::getUrl(isAbsolute: false))
            ->assertRedirect(route('filament.app.auth.login'));
    }

    public function test_user_who_already_chose_a_password_cannot_open_the_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(CreatePassword::getUrl(isAbsolute: false))
            ->assertForbidden();
    }

    public function test_dashboard_redirects_until_a_password_is_created(): void
    {
        $user = User::factory()->withoutChosenPassword()->create();

        $this->actingAs($user)
            ->get(Dashboard::getUrl(isAbsolute: false))
            ->assertRedirect(CreatePassword::getUrl());
    }

    public function test_creating_a_password_opens_the_dashboard(): void
    {
        $user = User::factory()->withoutChosenPassword()->create();

        Livewire::actingAs($user)
            ->test(CreatePassword::class)
            ->fillForm([
                'password' => 'new-password',
                'passwordConfirmation' => 'new-password',
            ], 'form')
            ->call('createPassword')
            ->assertHasNoFormErrors()
            ->assertRedirect(Filament::getUrl());

        $user->refresh();

        $this->assertTrue($user->hasSetPassword());
        $this->assertTrue(Hash::check('new-password', $user->password));

        $this->actingAs($user)
            ->get(Dashboard::getUrl(isAbsolute: false))
            ->assertOk();
    }

    public function test_password_shorter_than_the_minimum_is_rejected(): void
    {
        $user = User::factory()->withoutChosenPassword()->create();

        Livewire::actingAs($user)
            ->test(CreatePassword::class)
            ->fillForm([
                'password' => 'short',
                'passwordConfirmation' => 'short',
            ], 'form')
            ->call('createPassword')
            ->assertHasFormErrors(['password' => 'The password field must be at least 8 characters.']);

        $this->assertFalse($user->refresh()->hasSetPassword());
    }

    public function test_password_confirmation_must_match(): void
    {
        $user = User::factory()->withoutChosenPassword()->create();

        Livewire::actingAs($user)
            ->test(CreatePassword::class)
            ->fillForm([
                'password' => 'new-password',
                'passwordConfirmation' => 'other-password',
            ], 'form')
            ->call('createPassword')
            ->assertHasFormErrors(['password' => 'The password field must match confirm password.']);

        $this->assertFalse($user->refresh()->hasSetPassword());
    }
}
