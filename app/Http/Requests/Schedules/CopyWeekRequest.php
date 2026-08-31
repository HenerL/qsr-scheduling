<?php

namespace App\Http\Requests\Schedules;

use App\Http\Requests\BaseFormRequest;

class CopyWeekRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'source_week_start_date' => $this->dateRule(true),
        ];
    }

    public function messages(): array
    {
        return [
            ...$this->commonMessages(),
            'source_week_start_date.required' => 'Pick the week to copy from.',
        ];
    }
}
