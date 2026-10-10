<?php

namespace App\Http\Middleware;

use App\Filament\Pages\CreatePassword;
use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsSet
{
    /**
     * Send a verified user who has not chosen a password to the create-password page.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->hasVerifiedEmail() || $user->hasSetPassword()) {
            return $next($request);
        }

        $panel = Filament::getCurrentPanel();

        if ($panel !== null && $request->routeIs(
            CreatePassword::getRouteName($panel),
            $panel->generateRouteName('auth.logout'),
        )) {
            return $next($request);
        }

        return redirect()->to(CreatePassword::getUrl());
    }
}
