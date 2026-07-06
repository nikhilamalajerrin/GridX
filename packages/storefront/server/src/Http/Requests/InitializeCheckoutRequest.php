<?php

namespace GridX\Storefront\Http\Requests;

use GridX\Http\Requests\GridXRequest;
use GridX\Storefront\Rules\CustomerExists;
use GridX\Storefront\Rules\GatewayExists;
use Illuminate\Validation\Rule;

class InitializeCheckoutRequest extends GridXRequest
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
            'gateway'      => ['required', new GatewayExists()],
            'customer'     => ['required', new CustomerExists()],
            'cart'         => ['required', 'exists:storefront.carts,public_id'],
            'serviceQuote' => [Rule::requiredIf(fn () => !$this->boolean('pickup')), 'exists:service_quotes,public_id'],
            'cash'         => ['sometimes', 'boolean'],
            'pickup'       => ['sometimes', 'boolean'],
        ];
    }
}
