<?php

namespace App\Mappers\Schedules;

use App\Helpers\StringHelpers;
use App\Models\CrewStation;
use App\Models\Employee;
use Illuminate\Support\Carbon;

/**
 * The rows, columns and chip palette the board renders. Kept apart from
 * ScheduleShiftMapper so the phone list and the week grid read the same header data.
 */
class ScheduleBoardMapper
{
    private const DAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    /**
     * @param  array  $hoursByDayOfWeek  StoreOperatingHour rows keyed by day_of_week (0–6).
     */
    public static function day(string $date, array $hoursByDayOfWeek): array
    {
        $carbon = Carbon::parse($date);
        $dayOfWeek = (int) $carbon->dayOfWeek;
        $hours = $hoursByDayOfWeek[$dayOfWeek] ?? null;

        return [
            'date' => $date,
            'day_of_week' => $dayOfWeek,
            'day_name' => self::DAY_NAMES[$dayOfWeek],
            'day_short' => substr(self::DAY_NAMES[$dayOfWeek], 0, 3),
            'day_number' => (int) $carbon->day,
            'is_open' => $hours?->is_open ?? false,
            'is_24_hours' => $hours?->is_24_hours ?? false,
            'open_time' => self::time($hours?->open_time),
            'close_time' => self::time($hours?->close_time),
        ];
    }

    public static function employeeRow(Employee $employee, float $weeklyHours): array
    {
        return [
            'employee_id' => $employee->id,
            'employee_no' => $employee->employee_no,
            'contact_number' => $employee->contact_number,
            'full_name' => $employee->fullName(),
            'initials' => StringHelpers::initials($employee->first_name, $employee->last_name),
            'employee_type' => $employee->employee_type,
            'employment_status' => $employee->employment_status,
            'manager_position_id' => $employee->manager_position_id,
            'manager_position_name' => $employee->managerPosition?->position_name,
            'primary_station_id' => $employee->primary_station_id,
            'primary_station_name' => $employee->primaryStation?->station_name,
            'group_label' => $employee->isCrew()
                ? ($employee->primaryStation?->station_name ?? 'Unassigned')
                : ($employee->managerPosition?->position_name ?? 'Managers'),
            'station_ids' => $employee->trainedStationIds(),
            'max_hours_per_week' => $employee->max_hours_per_week,
            'weekly_hours' => round($weeklyHours, 2),
            'is_over_hours' => $employee->max_hours_per_week !== null
                && $weeklyHours > $employee->max_hours_per_week,
        ];
    }

    public static function station(CrewStation $station): array
    {
        return [
            'station_id' => $station->station_id,
            'station_name' => $station->station_name,
            'min_crew_required' => $station->min_crew_required,
        ];
    }

    private static function time(?string $time): ?string
    {
        return $time === null || $time === '' ? null : substr($time, 0, 5);
    }
}
