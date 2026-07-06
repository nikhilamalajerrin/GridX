<?php

namespace GridX\Http\Requests\Internal;

use GridX\Http\Requests\GridXRequest;

class UserForgotPasswordRequest extends GridXRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'email' => ['required', 'exists:users,email'],
        ];
    }
}
