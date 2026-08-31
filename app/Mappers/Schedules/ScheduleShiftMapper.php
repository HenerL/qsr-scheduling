<?php

namespace App\Mappers\Schedules;

use App\Helpers\TimeHelper;
use App\Models\ScheduleShift;

/**
 * The board's shift shape. This same array is what the rule CoreFunctions consume, so
 * there is one shift representation in the app rather than an API shape and a rules shape.
 */
class ScheduleShiftMapper
{
    public static function map(ScheduleShift $shift): array
    {
        $start = self::time($shift->start_time);
        $end = self::time($shift->end_time);

        return [
            'id' => $shift->id,
            'schedule_id' => $shift->schedule_id,
            'employee_id' => $shift->employee_id,
            'employee_name' => $shift->employee?->fullName(),
            'shift_date' => $shift->shift_date?->toDateString(),
            'shift_template_id' => $shift->shift_template_id,
            'template_name' => $shift->template?->template_name,
            'template_code' => $shift->template?->template_code,
            'color_hex' => $shift->template?->color_hex,
            'start_time' => $start,
            'end_time' => $end,
            'break_minutes' => $shift->break_minutes,
            'total_hours' => $start !== null && $end !== null
                ? TimeHelper::netHours($start, $end, (int) $shift->break_minutes)
                : 0.0,
            'crosses_midnight' => $start !== null && $end !== null && TimeHelper::crossesMidnight($start, $end),
            'crew_station_id' => $shift->crew_station_id,
            'station_name' => $shift->station?->station_name,
            'manager_position_id' => $shift->manager_position_id,
            'position_name' => $shift->position?->position_name,
            'is_rest_day' => $shift->is_rest_day,
            'status' => $shift->status,
            // Carried on the payload so the aggregation CoreFunction never re-derives it.
            'is_duty' => $shift->isDuty(),
            'is_revised' => $shift->is_revised,
            'remarks' => $shift->remarks,
        ];
    }

    private static function time(?string $time): ?string
    {
        return $time === null || $time === '' ? null : substr($time, 0, 5);
    }
}
