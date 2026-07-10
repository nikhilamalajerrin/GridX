<?php

namespace GridX\FleetOps\Http\Requests\Internal;

use GridX\Http\Requests\GridXRequest;

class AssignOrderRequest extends GridXRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return session('company');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'order'  => ['required', 'exists:orders,public_id'],
            'driver' => ['required', 'exists:drivers,public_id'],
        ];
    }
}
