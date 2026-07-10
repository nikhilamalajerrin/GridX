<?php

namespace GridX\FleetOps\Http\Requests;

use GridX\Http\Requests\GridXRequest;

/**
 * Validates the body of `POST /v1/customers/request-creation-code`.
 */
class VerifyCreateCustomerRequest extends GridXRequest
{
    public function authorize(): bool
    {
        return request()->session()->has('api_credential')
            || request()->session()->has('is_sanctum_token');
    }

    public function rules(): array
    {
        return [
            'mode'     => 'required|in:email,sms',
            'identity' => 'required|string',
            // Optional profile context — when provided, used to greet the
            // customer in the verification email and pre-seed the User row.
            'name'     => 'nullable|string|max:255',
            'phone'    => 'nullable|string|max:32',
        ];
    }
}
