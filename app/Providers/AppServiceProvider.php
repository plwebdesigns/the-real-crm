<?php

namespace App\Providers;

use App\Http\Responses\EmailVerificationResponse;
use App\Models\SaleDocument;
use Filament\Auth\Http\Responses\Contracts\EmailVerificationResponse as EmailVerificationResponseContract;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(EmailVerificationResponseContract::class, EmailVerificationResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        config([
            'livewire.temporary_file_upload.rules' => [
                'required',
                'file',
                'max:'.SaleDocument::MAX_SIZE_KILOBYTES,
            ],
        ]);
    }
}
