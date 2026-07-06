<?php

namespace GridX\Http\Requests\Internal;

use GridX\Http\Requests\GridXRequest;

class ResendUserInvite extends GridXRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->session()->has('company');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'user' => ['required', 'exists:users,uuid'],
        ];
    }
}
