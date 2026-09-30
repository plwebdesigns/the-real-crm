<?php

namespace Tests\Feature;

use Tests\TestCase;

class SiteWebManifestTest extends TestCase
{
    public function test_manifest_uses_company_name_and_icon_paths(): void
    {
        config(['app.company_name' => 'ICON Realty']);

        $this->get(route('site.webmanifest'))
            ->assertOk()
            ->assertHeader('content-type', 'application/manifest+json')
            ->assertJsonPath('name', 'ICON Realty')
            ->assertJsonPath('short_name', 'ICON Realty')
            ->assertJsonPath('icons.0.src', '/android-chrome-192x192.png')
            ->assertJsonPath('icons.0.sizes', '192x192')
            ->assertJsonPath('icons.1.src', '/android-chrome-512x512.png')
            ->assertJsonPath('icons.1.sizes', '512x512')
            ->assertJsonPath('theme_color', '#00aaaa')
            ->assertJsonPath('background_color', '#00aaaa')
            ->assertJsonPath('display', 'standalone');
    }

    public function test_manifest_falls_back_to_app_name_when_company_name_is_empty(): void
    {
        config([
            'app.company_name' => '',
            'app.name' => 'Real CRM',
        ]);

        $this->get(route('site.webmanifest'))
            ->assertJsonPath('name', 'Real CRM')
            ->assertJsonPath('short_name', 'Real CRM');
    }
}
