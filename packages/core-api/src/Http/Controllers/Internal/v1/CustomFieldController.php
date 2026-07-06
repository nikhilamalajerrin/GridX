<?php

namespace GridX\Http\Controllers\Internal\v1;

use GridX\Http\Controllers\GridXController;
use GridX\Http\Requests\Internal\CreateCustomFieldRequest;

class CustomFieldController extends GridXController
{
    /**
     * The resource to query.
     *
     * @var string
     */
    public $resource = 'custom_field';

    /**
     * The validation request to use.
     *
     * @var CreateCustomFieldRequest
     */
    public $request = CreateCustomFieldRequest::class;
}
