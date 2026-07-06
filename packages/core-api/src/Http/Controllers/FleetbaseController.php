<?php

namespace GridX\Http\Controllers;

use GridX\Traits\HasApiControllerBehavior;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

abstract class GridXController extends BaseController
{
    use AuthorizesRequests;
    use DispatchesJobs;
    use ValidatesRequests;
    use HasApiControllerBehavior;

    public string $namespace = '\\GridX';

    public function __construct(?Model $model = null, ?string $resource = null)
    {
        $this->setApiModel($model, $this->namespace);
        $this->setApiResource($resource, $this->namespace);
    }
}
