<?php

namespace App\Http\Requests\Schedules;

use App\Http\Requests\BaseFormRequest;

class BulkTagShiftsRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'shifts' => ['required', 'array', 'min:1', 'max:200'],
            'shifts.*.employee_id' => $this->foreignId('employees', true),
            'shifts.*.shift_date' => $this->dateRule(true),
            'shifts.*.shift_template_id' => $this->foreignId('shift_templates', false),
            'shifts.*.start_time' => $this->timeRule(false),
            'shifts.*.end_time' => $this->timeRule(false),
            'shifts.*.break_minutes' => ['nullable', 'integer', 'min:0', 'max:480'],
            'shifts.*.crew_station_id' => $this->foreignId('crew_stations', false, 'station_id'),
            'shifts.*.manager_position_id' => $this->foreignId('manager_positions', false, 'position_id'),
            'shifts.*.is_rest_day' => $this->booleanRule(),
            'shifts.*.remarks' => $this->nullableString(500),
        ];
    }

    public function messages(): array
    {
        return [
            ...$this->commonMessages(),
            'shifts.required' => 'Select at least one cell to tag.',
            'shifts.min' => 'Select at least one cell to tag.',
        ];
    }
}
