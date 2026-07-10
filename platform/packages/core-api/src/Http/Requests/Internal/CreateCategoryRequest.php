<?php

namespace GridX\Http\Requests\Internal;

use GridX\Http\Requests\GridXRequest;

class CreateCategoryRequest extends GridXRequest
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
            'name' => 'required|min:3',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'name.required' => 'The category name is required.',
            'name.min'      => 'The category name must be at least 3 characters.',
        ];
    }
}
