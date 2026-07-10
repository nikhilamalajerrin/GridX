<?php

namespace GridX\Http\Controllers\Internal\v1;

use GridX\Http\Controllers\GridXController;
use GridX\Http\Requests\Internal\CreateCategoryRequest;

class CategoryController extends GridXController
{
    /**
     * The resource to query.
     *
     * @var string
     */
    public $resource = 'category';

    /**
     * The validation request to use.
     *
     * @var CreateCategoryRequest
     */
    public $request = CreateCategoryRequest::class;
}
