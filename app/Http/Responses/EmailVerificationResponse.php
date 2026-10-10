<?php

namespace App\Http\Responses;

use App\Filament\Pages\CreatePassword;
use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\EmailVerificationResponse as EmailVerificationResponseContract;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class EmailVerificationResponse implements EmailVerificationResponseContract
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        $user = $request->user();

        if ($user instanceof User && $user->hasVerifiedEmail() && ! $user->hasSetPassword()) {
            return redirect()->to(CreatePassword::getUrl());
        }

        return redirect()->intended(Filament::getUrl());
    }
}
