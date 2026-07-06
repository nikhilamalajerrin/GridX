<?php

namespace GridX\FleetOps\Http\Requests;

use GridX\Http\Requests\GridXRequest;
use GridX\Rules\ExistsInAny;

class CreateTrackingNumberRequest extends GridXRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return request()->session()->has('api_credential');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'region' => 'required|string',
            'owner'  => ['required', new ExistsInAny(['orders', 'entities'], 'public_id')],
            'type'   => 'nullable|in:city,province,country',
            'status' => 'nullable|in:active,inactive',
        ];
    }
}
