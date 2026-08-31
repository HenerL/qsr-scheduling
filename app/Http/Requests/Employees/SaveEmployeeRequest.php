<?php

namespace App\Http\Requests\Employees;

use App\Http\Requests\BaseFormRequest;

class SaveEmployeeRequest extends BaseFormRequest
{
    use EmployeeStationRules;

    public function rules(): array
    {
        return [
            'first_name' => $this->requiredString(100),
            'last_name' => $this->requiredString(100),
            'middle_name' => $this->nullableString(100),
            'employee_type' => ['required', 'in:manager,crew'],
            'manager_position_id' => [
                'required_if:employee_type,manager',
                ...$this->foreignId('manager_positions', false, 'position_id'),
            ],
            'primary_station_id' => [
                'required_if:employee_type,crew',
                ...$this->foreignId('crew_stations', false, 'station_id'),
            ],
            'employment_status' => ['required', 'in:full_time,part_time,trainee'],
            'date_hired' => $this->dateRule(),
            'contact_number' => $this->nullableString(30),
            'max_hours_per_week' => ['nullable', 'integer', 'min:1', 'max:168'],
            'is_active' => $this->booleanRule(),
            'is_team_leader' => $this->booleanRule(),
            ...$this->stationRules(),
        ];
    }

    public function messages(): array
    {
        return [
            ...$this->commonMessages(),
            ...$this->stationMessages(),
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'employee_type.in' => 'Employee type must be manager or crew.',
            'manager_position_id.required_if' => 'A manager needs a position.',
            'primary_station_id.required_if' => 'A crew member needs a primary station.',
            'employment_status.in' => 'Employment status must be full time, part time or trainee.',
            'date_hired.required' => 'Date hired is required.',
            'max_hours_per_week.max' => 'Weekly hours cannot exceed 168.',
        ];
    }
}
