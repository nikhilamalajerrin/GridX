<?php

namespace GridX\FleetOps\Http\Requests;

use GridX\Http\Requests\GridXRequest;
use Illuminate\Validation\Rules\RequiredIf;

class CreateContactRequest extends GridXRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return request()->session()->has('storefront_key') || request()->session()->has('api_credential') || request()->session()->has('is_sanctum_token');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name'  => [new RequiredIf($this->isMethod('POST'))],
            'type'  => [new RequiredIf($this->isMethod('POST'))],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable'],
        ];
    }
}
