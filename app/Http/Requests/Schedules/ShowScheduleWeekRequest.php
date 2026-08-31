<?php

namespace App\Http\Requests\Schedules;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\FormRequestCommonRules;

class ShowScheduleWeekRequest extends BaseFormRequest
{
    use FormRequestCommonRules;

    public function rules(): array
    {
        // Optional: no date means the week that contains today.
        return [
            'week_start_date' => $this->dateRule(false),
        ];
    }

    public function messages(): array
    {
        return [
            'week_start_date.date_format' => 'Week start date must use the YYYY-MM-DD format.',
        ];
    }
}
