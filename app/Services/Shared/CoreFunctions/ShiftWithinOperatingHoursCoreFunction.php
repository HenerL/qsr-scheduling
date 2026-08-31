<?php

namespace App\Services\Shared\CoreFunctions;

use App\Helpers\TimeHelper;

/**
 * Block: a shift must sit inside the store's operating window for that weekday.
 *
 * Returns null when the rule passes, otherwise the rule text. Callers prefix the
 * employee/date context — CoreFunctions never format a sentence about a record.
 */
class ShiftWithinOperatingHoursCoreFunction
{
    /**
     * @param  array  $shift  start_time, end_time, is_rest_day
     * @param  array  $dayHours  is_open, is_24_hours, open_time, close_time
     */
    public static function check(array $shift, array $dayHours): ?string
    {
        if (!empty($shift['is_rest_day'])) {
            return null;
        }

        if (empty($dayHours['is_open'])) {
            return 'The store is closed on this date.';
        }

        if (!empty($dayHours['is_24_hours'])) {
            return null;
        }

        $open = $dayHours['open_time'] ?? null;
        $close = $dayHours['close_time'] ?? null;

        if ($open === null || $close === null) {
            return null;
        }

        [$shiftFrom, $shiftTo] = self::window((string) $shift['start_time'], (string) $shift['end_time']);
        [$openFrom, $closeTo] = self::window($open, $close);

        if ($shiftFrom < $openFrom || $shiftTo > $closeTo) {
            return sprintf(
                'Shift must fall inside store hours %s–%s.',
                substr($open, 0, 5),
                substr($close, 0, 5),
            );
        }

        return null;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private static function window(string $start, string $end): array
    {
        $from = TimeHelper::toMinutes($start);

        return [$from, $from + TimeHelper::durationMinutes($start, $end)];
    }
}
