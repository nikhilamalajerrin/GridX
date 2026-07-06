<?php

namespace GridX\Storefront\Http\Requests;

use GridX\Http\Requests\GridXRequest;
use GridX\Storefront\Rules\CustomerExists;

class StorefrontCustomerRequest extends GridXRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return session('storefront_key');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'customer'     => ['required', new CustomerExists()],
        ];
    }
}
