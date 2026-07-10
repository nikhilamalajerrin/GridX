<?php

namespace GridX\FleetOps\Http\Requests\Internal;

use GridX\Support\Auth;

class UpdateDriverRequest extends CreateDriverRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return Auth::can('fleet-ops update driver');
    }
}
