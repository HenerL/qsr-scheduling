<?php

namespace App\Services\Shared\CoreFunctions;

/**
 * Warn: an employee should get at least 8 hours off between consecutive shifts.
 */
class RestBetweenShiftsCoreFunction
{
    public const MIN_REST_MINUTES = 480;

    /**
     * @param  array  $neighbours  The employee's other shifts on the surrounding dates.
     */
    public static function check(array $shift, array $neighbours): ?string
    {
        $reference = (string) $shift['shift_date'];
        $window = ShiftWindowCoreFunction::absolute($shift, $reference);

        if ($window === null) {
            return null;
        }

        foreach ($neighbours as $neighbour) {
            if (isset($shift['id'], $neighbour['id']) && (int) $shift['id'] === (int) $neighbour['id']) {
                continue;
            }

            $neighbourWindow = ShiftWindowCoreFunction::absolute($neighbour, $reference);

            if ($neighbourWindow === null) {
                continue;
            }

            $gap = ShiftWindowCoreFunction::gapMinutes($window, $neighbourWindow);

            if ($gap !== null && $gap < self::MIN_REST_MINUTES) {
                return sprintf(
                    'Only %dh %02dm rest before or after the next shift (8h recommended).',
                    intdiv($gap, 60),
                    $gap % 60,
                );
            }
        }

        return null;
    }
}
