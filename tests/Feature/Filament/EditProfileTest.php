<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\EditProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EditProfileTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_an_agent_can_turn_on_follow_up_reminder_emails(): void
    {
        $agent = User::factory()->create();

        $this->assertFalse($agent->receives_task_reminder_emails);

        Livewire::actingAs($agent)
            ->test(EditProfile::class)
            ->assertFormSet([
                'receives_task_reminder_emails' => false,
            ])
            ->fillForm([
                'receives_task_reminder_emails' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($agent->fresh()->receives_task_reminder_emails);
    }
}
