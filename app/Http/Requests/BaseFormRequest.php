<?php

namespace App\Http\Requests;

use App\Helpers\QueryResultHelperV2;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class BaseFormRequest extends FormRequest
{
    use FormRequestCommonRules;
    use FormRequestCommonMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function messages(): array
    {
        return $this->commonMessages();
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            QueryResultHelperV2::onBadRequest($validator->errors()->toArray())
        );
    }
}
