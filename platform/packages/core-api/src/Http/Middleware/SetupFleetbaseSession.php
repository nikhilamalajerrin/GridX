<?php

namespace GridX\Http\Middleware;

use GridX\Support\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class SetupGridXSession
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     */
    public function handle($request, \Closure $next)
    {
        $user = $request->user();
        Auth::setSession($user);
        Auth::setSandboxSession($request);

        if (method_exists($user, 'currentAccessToken')) {
            $personalAccessToken = $user->currentAccessToken();
            if ($personalAccessToken && $personalAccessToken instanceof PersonalAccessToken) {
                Auth::setApiKey($personalAccessToken);
            }
        }

        return $next($request);
    }
}
