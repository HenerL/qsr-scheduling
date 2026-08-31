<?php

namespace App\Services\Shared\CoreFunctions;

/**
 * Block: a rest day and a duty shift cannot share the same date.
 */
class RestDayConflictCoreFunction
{
    /**
     * @param  array  $existing  Other shifts for the same employee, cancelled ones already filtered out.
     */
    public static function check(array $candidate, array $existing): ?string
    {
        $date = (string) $candidate['shift_date'];
        $candidateIsRestDay = !empty($candidate['is_rest_day']);

        foreach ($existing as $shift) {
            if ((string) $shift['shift_date'] !== $date) {
                continue;
            }

            if (isset($candidate['id'], $shift['id']) && (int) $candidate['id'] === (int) $shift['id']) {
                continue;
            }

            $shiftIsRestDay = !empty($shift['is_rest_day']);

            if ($candidateIsRestDay && !$shiftIsRestDay) {
                return 'This date already has a shift, so it cannot be a rest day.';
            }

            if (!$candidateIsRestDay && $shiftIsRestDay) {
                return 'This date is marked as a rest day.';
            }

            if ($candidateIsRestDay && $shiftIsRestDay) {
                return 'This date is already marked as a rest day.';
            }
        }

        return null;
    }
}
