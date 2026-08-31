<?php

namespace App\Http\Requests\ManagerPositions;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\FormRequestCommonRules;

class SaveManagerPositionRequest extends BaseFormRequest
{
    use FormRequestCommonRules;

    public function rules(): array
    {
        return [
            'position_name' => $this->requiredString(100),
            'position_description' => $this->nullableString(1000),
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => $this->booleanRule(),
        ];
    }

    public function messages(): array
    {
        return [
            'position_name.required' => 'Position name is required.',
        ];
    }
}
