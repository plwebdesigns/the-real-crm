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
}
