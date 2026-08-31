<?php

namespace App\Services\Shared\CoreFunctions;

/**
 * Block: one employee cannot work two overlapping shifts. Shifts touching at the
 * boundary (14:00–22:00 then 22:00–06:00) are not an overlap.
 */
class ShiftOverlapCoreFunction
{
    /**
     * @param  array  $existing  Other shifts for the same employee, cancelled ones already filtered out.
     * @return array|null The first shift that overlaps the candidate.
     */
    public static function firstOverlap(array $candidate, array $existing): ?array
    {
        $reference = (string) $candidate['shift_date'];
        $candidateWindow = ShiftWindowCoreFunction::absolute($candidate, $reference);

        if ($candidateWindow === null) {
            return null;
        }

        foreach ($existing as $shift) {
            if (self::isSameRecord($candidate, $shift)) {
                continue;
            }

            $window = ShiftWindowCoreFunction::absolute($shift, $reference);

            if ($window !== null && ShiftWindowCoreFunction::overlaps($candidateWindow, $window)) {
                return $shift;
            }
        }

        return null;
    }

    private static function isSameRecord(array $candidate, array $shift): bool
    {
        return isset($candidate['id'], $shift['id']) && (int) $candidate['id'] === (int) $shift['id'];
    }
}
