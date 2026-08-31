<?php

namespace App\Http\Requests\Schedules;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\FormRequestCommonRules;

class ShowScheduleMonthRequest extends BaseFormRequest
{
    use FormRequestCommonRules;

    public function rules(): array
    {
        return [
            'month' => 'nullable|date_format:Y-m',
        ];
    }

    public function messages(): array
    {
        return [
            'month.date_format' => 'Month must use the YYYY-MM format.',
        ];
    }
}
