<?php

namespace GridX\RegistryBridge\Http\Requests;

use GridX\Http\Requests\GridXRequest;

class RegistryExtensionActionRequest extends GridXRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return session('is_admin') === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'id' => 'required|exists:registry_extensions,uuid',
        ];
    }
}
