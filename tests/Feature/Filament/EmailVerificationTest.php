<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use Database\Seeders\LocationSeeder;
use Database\Seeders\UserSeeder;
use Filament\Auth\Notifications\VerifyEmail;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_creating_an_agent_sends_a_verification_email_and_leaves_the_address_unverified(): void
    {
        $admin = User::factory()->admin()->create();

        Notification::fake();

        Livewire::actingAs($admin)
            ->test(CreateUser::class)
            ->fillForm([
                'name' => 'Jordan Agent',
                'email' => 'jordan@example.com',
                'password' => 'password',
                'is_admin' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $agent = User::query()->where('email', 'jordan@example.com')->firstOrFail();

        $this->assertNull($agent->email_verified_at);
        Notification::assertSentTo($agent, VerifyEmail::class);
    }

    public function test_unverified_user_is_redirected_to_the_verification_prompt(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(Dashboard::getUrl(isAbsolute: false))
            ->assertRedirect(Filament::getEmailVerificationPromptUrl());
    }

    public function test_verified_user_can_open_the_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(Dashboard::getUrl(isAbsolute: false))
            ->assertOk();
    }

    public function test_signed_verification_link_marks_the_email_as_verified(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(Filament::getVerifyEmailUrl($user))
            ->assertRedirect();

        $this->assertTrue($user->refresh()->hasVerifiedEmail());
    }

    public function test_changing_an_agents_email_requires_verification_again(): void
    {
        $admin = User::factory()->admin()->create();
        $agent = User::factory()->for($admin->location)->create();

        Notification::fake();

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $agent->getRouteKey()])
            ->fillForm([
                'email' => 'renamed@example.com',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $agent->refresh();

        $this->assertSame('renamed@example.com', $agent->email);
        $this->assertNull($agent->email_verified_at);
        Notification::assertSentTo($agent, VerifyEmail::class);
    }

    public function test_saving_an_agent_without_an_email_change_keeps_the_address_verified(): void
    {
        $admin = User::factory()->admin()->create();
        $agent = User::factory()->for($admin->location)->create();

        Notification::fake();

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $agent->getRouteKey()])
            ->fillForm([
                'name' => 'Renamed Agent',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $agent->refresh();

        $this->assertSame('Renamed Agent', $agent->name);
        $this->assertTrue($agent->hasVerifiedEmail());
        Notification::assertNothingSent();
    }

    public function test_seeded_demo_users_are_verified_and_receive_no_verification_email(): void
    {
        Notification::fake();

        $this->seed(LocationSeeder::class);
        $this->seed(UserSeeder::class);

        $this->assertSame(0, User::query()->whereNull('email_verified_at')->count());
        $this->assertTrue(User::query()->where('email', 'demo@example.com')->firstOrFail()->hasVerifiedEmail());
        Notification::assertNothingSent();
    }
}
