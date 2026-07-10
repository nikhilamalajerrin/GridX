<?php

namespace GridX\Http\Middleware;

use GridX\Support\Auth;
use GridX\Traits\CustomMiddleware;

class AuthorizationGuard
{
    use CustomMiddleware;

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     */
    public function handle($request, \Closure $next)
    {
        $requiredPermissions = Auth::resolvePermissionsFromRequest($request);
        $user                = Auth::getUserFromSession($request);
        if ($user->isNotAdmin() && $requiredPermissions->isNotEmpty() && $user->doesntHavePermissions($requiredPermissions)) {
            return response()->authorizationError();
        }

        return $next($request);
    }
}
