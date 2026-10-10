<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\CreatePassword;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use Database\Seeders\LocationSeeder;
use Database\Seeders\UserSeeder;
use Filament\Auth\Notifications\VerifyEmail;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
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
                'is_admin' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $agent = User::query()->where('email', 'jordan@example.com')->firstOrFail();

        $this->assertNull($agent->email_verified_at);
        $this->assertNull($agent->password_set_at);
        $this->assertFalse(Hash::check('password', $agent->password));
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
            ->assertRedirect(Filament::getUrl());

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
        $this->assertTrue($agent->hasSetPassword());
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
        $demo = User::query()->where('email', 'demo@example.com')->firstOrFail();

        $this->assertTrue($demo->hasVerifiedEmail());
        $this->assertTrue($demo->hasSetPassword());
        Notification::assertNothingSent();
    }

    public function test_signed_verification_link_signs_the_agent_in_and_opens_create_password(): void
    {
        $agent = User::factory()->unverified()->withoutChosenPassword()->create();

        Notification::fake();

        $agent->sendEmailVerificationNotification();

        $url = $this->sentVerificationUrl($agent);

        $this->get($url)
            ->assertRedirect(CreatePassword::getUrl());

        $this->assertAuthenticatedAs($agent);
        $this->assertTrue($agent->refresh()->hasVerifiedEmail());
        $this->assertFalse($agent->hasSetPassword());

        $this->get($url)
            ->assertRedirect(CreatePassword::getUrl());
    }

    public function test_verification_link_with_a_bad_signature_is_rejected(): void
    {
        $agent = User::factory()->unverified()->withoutChosenPassword()->create();

        $this->get(route('filament.app.auth.email-verification.accept', [
            'user' => $agent,
            'hash' => sha1($agent->getEmailForVerification()),
        ]))->assertForbidden();

        $this->assertFalse($agent->refresh()->hasVerifiedEmail());
        $this->assertGuest();
    }

    public function test_verification_link_with_the_wrong_email_hash_is_rejected(): void
    {
        $agent = User::factory()->unverified()->withoutChosenPassword()->create();

        $url = URL::temporarySignedRoute(
            'filament.app.auth.email-verification.accept',
            now()->addMinutes(60),
            [
                'user' => $agent->getKey(),
                'hash' => sha1('not-the-email'),
            ],
        );

        $this->get($url)->assertForbidden();

        $this->assertFalse($agent->refresh()->hasVerifiedEmail());
    }

    public function test_verification_link_for_a_missing_user_is_not_found(): void
    {
        $url = URL::temporarySignedRoute(
            'filament.app.auth.email-verification.accept',
            now()->addMinutes(60),
            [
                'user' => 999999,
                'hash' => sha1('missing@example.com'),
            ],
        );

        $this->get($url)->assertNotFound();
    }

    public function test_verification_link_does_not_switch_accounts(): void
    {
        $agent = User::factory()->unverified()->withoutChosenPassword()->create();
        $other = User::factory()->create();

        Notification::fake();

        $agent->sendEmailVerificationNotification();

        $this->actingAs($other)
            ->get($this->sentVerificationUrl($agent))
            ->assertForbidden();

        $this->assertAuthenticatedAs($other);
        $this->assertFalse($agent->refresh()->hasVerifiedEmail());
    }

    public function test_verification_link_sends_an_existing_user_to_login(): void
    {
        $agent = User::factory()->unverified()->create();

        Notification::fake();

        $agent->sendEmailVerificationNotification();

        $this->get($this->sentVerificationUrl($agent))
            ->assertRedirect(Filament::getLoginUrl());

        $this->assertGuest();
        $this->assertTrue($agent->refresh()->hasVerifiedEmail());
    }

    public function test_signed_in_user_who_already_has_a_password_is_not_sent_to_create_password(): void
    {
        $agent = User::factory()->unverified()->create();

        Notification::fake();

        $agent->sendEmailVerificationNotification();

        $this->actingAs($agent)
            ->get($this->sentVerificationUrl($agent))
            ->assertRedirect(Filament::getUrl());

        $this->assertTrue($agent->refresh()->hasVerifiedEmail());
    }

    public function test_logged_in_verification_sends_a_user_without_a_password_to_create_one(): void
    {
        $agent = User::factory()->unverified()->withoutChosenPassword()->create();

        $this->actingAs($agent)
            ->get(Filament::getVerifyEmailUrl($agent))
            ->assertRedirect(CreatePassword::getUrl());

        $this->assertTrue($agent->refresh()->hasVerifiedEmail());
    }

    public function test_unverified_user_without_a_password_is_sent_to_the_verification_prompt(): void
    {
        $user = User::factory()->unverified()->withoutChosenPassword()->create();

        $this->actingAs($user)
            ->get(Dashboard::getUrl(isAbsolute: false))
            ->assertRedirect(Filament::getEmailVerificationPromptUrl());
    }

    private function sentVerificationUrl(User $user): string
    {
        $url = null;

        Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification) use (&$url): bool {
            $url = $notification->url;

            return filled($url);
        });

        if (! is_string($url)) {
            $this->fail('Verification email did not include a link.');
        }

        return $url;
    }
}
