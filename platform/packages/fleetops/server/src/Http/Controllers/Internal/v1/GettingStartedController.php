<?php

namespace GridX\FleetOps\Http\Controllers\Internal\v1;

use GridX\FleetOps\Support\GettingStarted;
use GridX\Http\Controllers\Controller;
use Illuminate\Http\Request;

class GettingStartedController extends Controller
{
    public function status(Request $request)
    {
        return response()->json(
            GettingStarted::forCompany($request->user()->company)->get()
        );
    }
}
