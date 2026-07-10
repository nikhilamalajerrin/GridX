<?php

namespace GridX\Ai\Http\Controllers\Internal;

use GridX\Ai\Support\AiCapabilityRegistry;
use GridX\Http\Controllers\Controller;

class AiToolController extends Controller
{
    public function index(AiCapabilityRegistry $registry)
    {
        return response()->json([
            'tools' => $registry->list(),
        ]);
    }
}
