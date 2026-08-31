<?php

namespace App\Http\Requests\Store;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\FormRequestCommonRules;

class SaveStoreRequest extends BaseFormRequest
{
    use FormRequestCommonRules;

    public function rules(): array
    {
        return [
            'store_name' => $this->requiredString(),
            'branch_name' => $this->nullableString(),
            'address' => $this->nullableString(500),
            'contact_number' => $this->nullableString(50),
            'timezone' => ['required', 'string', 'timezone:all'],
            'week_starts_on' => ['required', 'integer', 'in:0,1'],
            'max_consecutive_duty_days' => ['required', 'integer', 'min:1', 'max:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'store_name.required' => 'Store name is required.',
            'timezone.required' => 'Timezone is required.',
            'week_starts_on.in' => 'Week must start on Sunday or Monday.',
            'max_consecutive_duty_days.min' => 'Consecutive duty limit must be at least 1 day.',
            'max_consecutive_duty_days.max' => 'Consecutive duty limit cannot exceed 10 days.',
        ];
    }
}
