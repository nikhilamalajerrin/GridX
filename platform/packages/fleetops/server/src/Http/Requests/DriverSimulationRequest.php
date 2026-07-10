<?php

namespace GridX\FleetOps\Http\Requests;

use GridX\FleetOps\Rules\ResolvablePoint;
use GridX\Http\Requests\GridXRequest;
use Illuminate\Validation\Rule;

class DriverSimulationRequest extends GridXRequest
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
            'start' => [Rule::requiredIf(
                function () {
                    return $this->missing('order');
                }
            ), new ResolvablePoint()],
            'end' => [Rule::requiredIf(
                function () {
                    return $this->missing('order');
                }
            ), new ResolvablePoint()],
            'order' => [Rule::requiredIf(
                function () {
                    return $this->input('action', 'drive') === 'order';
                }
            ), 'string', 'exists:orders,public_id'],
        ];
    }
}
