<?php

namespace GridX\RegistryBridge\Http\Requests;

use GridX\Http\Requests\GridXRequest;

class InstallExtensionRequest extends GridXRequest
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
            'extension' => ['required', 'exists:registry_extensions,public_id'],
        ];
    }
}
