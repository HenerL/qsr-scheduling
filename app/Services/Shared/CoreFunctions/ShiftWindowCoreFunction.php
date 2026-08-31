<?php

namespace App\Services\Shared\CoreFunctions;

use App\Helpers\DateHelper;
use App\Helpers\TimeHelper;

/**
 * Turns a dated shift into an absolute minute window so overnight shifts can be
 * compared across dates. Shared by the overlap block and the rest-gap warning so
 * neither re-implements date + time arithmetic.
 *
 * A shift array carries: shift_date, start_time, end_time, is_rest_day.
 */
class ShiftWindowCoreFunction
{
    /**
     * @return array{0: int, 1: int}|null Null when the shift has no times (rest day).
     */
    public static function absolute(array $shift, string $referenceDate): ?array
    {
        if (!empty($shift['is_rest_day'])) {
            return null;
        }

        $start = $shift['start_time'] ?? null;
        $end = $shift['end_time'] ?? null;

        if ($start === null || $end === null || $start === '' || $end === '') {
            return null;
        }

        $offset = DateHelper::daysBetween($referenceDate, (string) $shift['shift_date']) * 1440;
        $from = $offset + TimeHelper::toMinutes($start);

        return [$from, $from + TimeHelper::durationMinutes($start, $end)];
    }

    public static function overlaps(array $windowA, array $windowB): bool
    {
        return $windowA[0] < $windowB[1] && $windowB[0] < $windowA[1];
    }

    /**
     * Minutes of rest between two windows. Null when they overlap — that is the
     * overlap block's job to report, not the rest warning's.
     */
    public static function gapMinutes(array $windowA, array $windowB): ?int
    {
        if (self::overlaps($windowA, $windowB)) {
            return null;
        }

        return $windowA[0] >= $windowB[1]
            ? $windowA[0] - $windowB[1]
            : $windowB[0] - $windowA[1];
    }
}
