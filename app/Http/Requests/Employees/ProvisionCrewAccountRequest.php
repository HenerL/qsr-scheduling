<?php

namespace App\Http\Requests\Employees;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rules\Password;

class ProvisionCrewAccountRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:filter', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8)],
        ];
    }

    public function messages(): array
    {
        return [
            ...$this->commonMessages(),
            'email.required' => 'Email is required.',
            'email.email' => 'Enter a valid email address.',
            'email.unique' => 'An account with this email already exists.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
        ];
    }
}
