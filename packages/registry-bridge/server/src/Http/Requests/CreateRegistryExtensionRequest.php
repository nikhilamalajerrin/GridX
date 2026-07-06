<?php

namespace GridX\RegistryBridge\Http\Requests;

use GridX\Http\Requests\GridXRequest;

class CreateRegistryExtensionRequest extends GridXRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return session('company');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name'        => ['required', 'min:3', 'unique:registry_extensions,name'],
            'description' => ['required', 'min:12'],
        ];
    }
}
