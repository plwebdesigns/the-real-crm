<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

Route::get('/site.webmanifest', function (): JsonResponse {
    $name = config('app.company_name') ?: config('app.name');

    return response()->json([
        'name' => $name,
        'short_name' => $name,
        'icons' => [
            [
                'src' => '/android-chrome-192x192.png',
                'sizes' => '192x192',
                'type' => 'image/png',
            ],
            [
                'src' => '/android-chrome-512x512.png',
                'sizes' => '512x512',
                'type' => 'image/png',
            ],
        ],
        'theme_color' => '#00aaaa',
        'background_color' => '#00aaaa',
        'display' => 'standalone',
    ], headers: [
        'Content-Type' => 'application/manifest+json',
    ]);
})->name('site.webmanifest');
