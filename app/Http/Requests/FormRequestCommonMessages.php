<?php

namespace App\Http\Requests;

trait FormRequestCommonMessages
{
    protected function commonMessages(): array
    {
        return [
            'required' => 'The :attribute field is required.',
            'string' => 'The :attribute must be text.',
            'max' => 'The :attribute may not exceed :max characters.',
            'min' => 'The :attribute must be at least :min.',
            'email' => 'Enter a valid email address.',
            'unique' => 'The :attribute is already in use.',
            'exists' => 'The selected :attribute is invalid.',
            'integer' => 'The :attribute must be a whole number.',
            'boolean' => 'The :attribute must be true or false.',
            'numeric' => 'The :attribute must be a number.',
            'date_format' => 'The :attribute does not match the required format.',
            'in' => 'The selected :attribute is invalid.',
            'regex' => 'The :attribute format is invalid.',
        ];
    }
}
