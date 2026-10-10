<?php

namespace App\Http\Controllers\Auth;

use App\Filament\Pages\CreatePassword;
use App\Http\Controllers\Controller;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AcceptEmailVerificationController extends Controller
{
    public function __invoke(Request $request, User $user, string $hash): RedirectResponse
    {
        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            abort(403);
        }

        $authenticatedUser = $request->user();

        if ($authenticatedUser instanceof User && ! $authenticatedUser->is($user)) {
            abort(403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        if (! $user->hasSetPassword()) {
            Filament::auth()->login($user);
            $request->session()->regenerate();

            return redirect()->to(CreatePassword::getUrl());
        }

        if ($authenticatedUser instanceof User) {
            return redirect()->intended(Filament::getUrl());
        }

        return redirect()->to(Filament::getLoginUrl());
    }
}
