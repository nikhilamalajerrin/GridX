<?php

namespace GridX\CustomerPortal\Http\Controllers\Internal\v1;

use GridX\CustomerPortal\Services\PortalConfigService;
use GridX\Http\Controllers\Controller;

class PaymentController extends Controller
{
    public function __construct(protected PortalConfigService $portalConfig)
    {
    }

    public function config()
    {
        return response()->json($this->portalConfig->paymentsConfig());
    }
}
