<?php

namespace GridX\Http\Controllers\Internal\v1;

use GridX\Http\Controllers\GridXController;

class WebhookRequestLogController extends GridXController
{
    /**
     * The resource to query.
     *
     * @var string
     */
    public $resource = 'webhook_request_log';

    /**
     * The service which this controller belongs to.
     *
     * @var string
     */
    public $service = 'developers';
}
