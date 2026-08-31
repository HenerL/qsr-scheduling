<?php

namespace App\Services\Shared\CoreFunctions;

/**
 * Warn: weekly paid hours should stay within the employee's contracted maximum.
 */
class WeeklyHoursCoreFunction
{
    public static function check(float $totalHours, ?int $maxHours): ?string
    {
        if ($maxHours === null || $maxHours <= 0 || $totalHours <= $maxHours) {
            return null;
        }

        return sprintf('Weekly hours %sh exceed the %dh limit.', round($totalHours, 2), $maxHours);
    }
}
