<?php

namespace App\Services\Shared\CoreFunctions;

/**
 * Rolls a list of mapped shifts up into the totals the board header, the coverage
 * warning and the week summary all need. Only duty shifts count — a rest day or a
 * cancelled shift carries no hours and staffs no station.
 *
 * Every method takes ScheduleShiftMapper output, so `is_duty` and `total_hours` are
 * already decided by the model and the mapper rather than re-derived here.
 */
class ScheduleTotalsCoreFunction
{
    /**
     * @return array<int, float> employee_id => paid hours
     */
    public static function hoursByEmployee(array $shifts): array
    {
        $totals = [];

        foreach (self::duty($shifts) as $shift) {
            $employeeId = (int) $shift['employee_id'];
            $totals[$employeeId] = round(($totals[$employeeId] ?? 0.0) + (float) $shift['total_hours'], 2);
        }

        return $totals;
    }

    /**
     * @return array<string, float> date => paid hours
     */
    public static function hoursByDate(array $shifts): array
    {
        $totals = [];

        foreach (self::duty($shifts) as $shift) {
            $date = (string) $shift['shift_date'];
            $totals[$date] = round(($totals[$date] ?? 0.0) + (float) $shift['total_hours'], 2);
        }

        return $totals;
    }

    /**
     * @return array<string, int> date => distinct employees on duty
     */
    public static function headcountByDate(array $shifts): array
    {
        $employeesByDate = [];

        foreach (self::duty($shifts) as $shift) {
            $employeesByDate[(string) $shift['shift_date']][(int) $shift['employee_id']] = true;
        }

        return array_map('count', $employeesByDate);
    }

    /**
     * @return array<string, array<int, int>> date => [station_id => assigned crew]
     */
    public static function stationCountsByDate(array $shifts): array
    {
        $counts = [];

        foreach (self::duty($shifts) as $shift) {
            if ($shift['crew_station_id'] === null) {
                continue;
            }

            $date = (string) $shift['shift_date'];
            $stationId = (int) $shift['crew_station_id'];
            $counts[$date][$stationId] = ($counts[$date][$stationId] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @return array Shifts limited to the given dates, in the order received.
     */
    public static function onlyDates(array $shifts, array $dates): array
    {
        $allowed = array_fill_keys($dates, true);

        return array_values(array_filter(
            $shifts,
            static fn (array $shift) => isset($allowed[(string) $shift['shift_date']]),
        ));
    }

    private static function duty(array $shifts): array
    {
        return array_filter($shifts, static fn (array $shift) => !empty($shift['is_duty']));
    }
}
