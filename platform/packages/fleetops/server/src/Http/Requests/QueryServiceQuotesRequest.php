<?php

namespace GridX\FleetOps\Http\Requests;

use GridX\Http\Requests\GridXRequest;
use GridX\Rules\ExistsInAny;
use Illuminate\Validation\Rule;

class QueryServiceQuotesRequest extends GridXRequest
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
            'payload'      => ['nullable', 'required_without_all:waypoints,pickup,dropoff', Rule::exists('payloads', 'public_id')->whereNull('deleted_at')],
            'service_type' => ['nullable', Rule::exists('service_rates', 'service_type')->whereNull('deleted_at')],
            'pickup'       => ['nullable', 'required_without_all:payload,waypoints'],
            'dropoff'      => ['nullable', 'required_without_all:payload,waypoints'],
            'waypoints'    => ['nullable', 'array', 'required_without_all:payload,pickup,dropoff'],
            'facilitator'  => ['nullable', new ExistsInAny(['vendors', 'integrated_vendors', 'contacts'], ['public_id', 'provider'])],
            'scheduled_at' => ['nullable', 'date'],
            'cod'          => ['nullable'],
            'currency'     => ['nullable', 'string', 'size:3'],
            'distance'     => ['nullable'],
            'time'         => ['nullable'],
        ];
    }
}
