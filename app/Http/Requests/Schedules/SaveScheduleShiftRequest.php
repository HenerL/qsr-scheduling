<?php

namespace App\Http\Requests\Schedules;

use App\Http\Requests\BaseFormRequest;

class SaveScheduleShiftRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'employee_id' => $this->foreignId('employees', true),
            'shift_date' => $this->dateRule(true),
            'shift_template_id' => $this->foreignId('shift_templates', false),
            'start_time' => $this->timeRule(false),
            'end_time' => $this->timeRule(false),
            'break_minutes' => ['nullable', 'integer', 'min:0', 'max:480'],
            'crew_station_id' => $this->foreignId('crew_stations', false, 'station_id'),
            'manager_position_id' => $this->foreignId('manager_positions', false, 'position_id'),
            'is_rest_day' => $this->booleanRule(),
            'remarks' => $this->nullableString(500),
        ];
    }
}
