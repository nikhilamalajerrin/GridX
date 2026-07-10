<?php

namespace GridX\FleetOps\Http\Requests\Internal;

use GridX\Http\Requests\GridXRequest;
use GridX\Support\Auth;
use Illuminate\Validation\Rule;

class CreateOrderConfigRequest extends GridXRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return Auth::can('fleet-ops create order-config');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => [
                'required',
                Rule::unique('order_configs', 'name')
                    ->where('company_uuid', request()->session()->get('company'))->whereNull('deleted_at'),
            ],
            'key' => [
                'required',
                Rule::unique('order_configs', 'key')
                    ->where('company_uuid', request()->session()->get('company'))->whereNull('deleted_at'),
            ],
        ];
    }
}
