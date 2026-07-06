<?php

namespace GridX\Http\Controllers\Internal\v1;

use GridX\Http\Controllers\GridXController;

class ApiEventController extends GridXController
{
    /**
     * The resource to query.
     *
     * @var string
     */
    public $resource = 'api_event';

    /**
     * The service which this controller belongs to.
     *
     * @var string
     */
    public $service = 'developers';
}
