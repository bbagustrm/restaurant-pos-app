<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

/**
 * Custom Fortify login response that routes users to their role-specific home.
 *
 *  - super_admin → /admin
 *  - cashier     → /pos
 *  - kitchen     → /kitchen
 *  - fallback    → config('fortify.home')
 */
class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): Response|RedirectResponse
    {
        /** @var Request $request */
        $user = $request->user();

        $target = match (true) {
            $user?->hasRole('super_admin') => '/admin',
            $user?->hasRole('cashier') => '/pos',
            $user?->hasRole('kitchen') => '/kitchen',
            default => config('fortify.home', '/dashboard'),
        };

        return redirect()->intended($target);
    }
}
