<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirect an authenticated user to the home route that matches their role.
 *
 * Used as a post-login landing route: Fortify sends users to `/redirect-by-role`,
 * this middleware bounces them to `/admin`, `/pos`, or `/kitchen`.
 */
class RedirectByRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        return match (true) {
            $user->hasRole('super_admin') => redirect('/admin'),
            $user->hasRole('cashier') => redirect('/pos'),
            $user->hasRole('kitchen') => redirect('/kitchen'),
            default => $next($request),
        };
    }
}
