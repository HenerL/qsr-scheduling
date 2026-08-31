<?php

namespace App\Mappers\Employees;

use App\Helpers\StringHelpers;
use App\Models\Employee;
use App\Models\EmployeeStation;

class EmployeeMapper
{
    public static function map(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'store_id' => $employee->store_id,
            'employee_no' => $employee->employee_no,
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'middle_name' => $employee->middle_name,
            'full_name' => StringHelpers::fullName($employee->first_name, $employee->last_name, $employee->middle_name),
            'employee_type' => $employee->employee_type,
            'manager_position_id' => $employee->manager_position_id,
            'manager_position_name' => $employee->managerPosition?->position_name,
            'primary_station_id' => $employee->primary_station_id,
            'primary_station_name' => $employee->primaryStation?->station_name,
            'employment_status' => $employee->employment_status,
            'date_hired' => $employee->date_hired?->toDateString(),
            'contact_number' => $employee->contact_number,
            'max_hours_per_week' => $employee->max_hours_per_week,
            'user_id' => $employee->user_id,
            'has_login' => $employee->user_id !== null,
            'is_active' => $employee->is_active,
            'is_team_leader' => $employee->isCrewTeamLeader(),
            'stations' => $employee->stations
                ->map(static fn (EmployeeStation $station) => self::mapStation($station))
                ->values()
                ->all(),
        ];
    }

    public static function mapStation(EmployeeStation $employeeStation): array
    {
        return [
            'station_id' => $employeeStation->station_id,
            'station_name' => $employeeStation->station?->station_name,
            'proficiency' => $employeeStation->proficiency,
        ];
    }
}
