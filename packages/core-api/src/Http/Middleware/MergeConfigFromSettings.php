<?php

namespace GridX\Http\Middleware;

use GridX\Support\EnvironmentMapper;
use Illuminate\Http\Request;

class MergeConfigFromSettings
{
    public function handle(Request $request, \Closure $next)
    {
        EnvironmentMapper::mergeConfigFromSettingsOptimized();

        return $next($request);
    }
}
