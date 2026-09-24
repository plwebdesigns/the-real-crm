<?php

namespace Tests\Feature\Filament;

use Tests\TestCase;

class AppPanelProviderTest extends TestCase
{
    public function test_login_page_renders_company_name_as_logo(): void
    {
        config(['app.company_name' => 'ICON Realty']);

        $this->get(route('filament.app.auth.login'))
            ->assertSee('ICON Realty');
    }

    public function test_login_page_shows_demo_accounts_and_github_link(): void
    {
        $this->get(route('filament.app.auth.login'))
            ->assertSee('Demo accounts')
            ->assertSee('Password123')
            ->assertSee('superadmin@example.com')
            ->assertSee('downtownadmin@example.com')
            ->assertSee('westsideadmin@example.com')
            ->assertSee('demo@example.com')
            ->assertSee('https://github.com/plwebdesigns/the-real-crm', false)
            ->assertSee('View on GitHub');
    }
}
