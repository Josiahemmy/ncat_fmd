<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces users flagged `password_change_required` (admin-provisioned accounts,
 * incl. the seeded Super Admin) to set a new password before doing anything
 * else. Applies to authenticated requests only; the change screen and logout
 * are exempt so the user isn't trapped.
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->password_change_required
            && ! $request->routeIs('password.change')
            && ! $request->routeIs('password.change.update')
            && ! $request->routeIs('logout')) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
