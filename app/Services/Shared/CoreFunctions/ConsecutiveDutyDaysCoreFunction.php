<?php

namespace App\Services\Shared\CoreFunctions;

use App\Helpers\DateHelper;

/**
 * Warn: too many consecutive duty days with no rest.
 *
 * The only rule that cannot be judged from one week alone, so it takes a flat list of
 * duty dates spanning the week padded by 6 days either side (PLAN §5). A date is absent
 * from the list when it is a rest day, cancelled, or untagged — all three break the run.
 */
class ConsecutiveDutyDaysCoreFunction
{
    /**
     * @param  array  $dutyDates  'Y-m-d' strings for one employee, order irrelevant.
     * @return int Length of the run containing $asOfDate; 0 when that date is not duty.
     */
    public static function longestRun(array $dutyDates, string $asOfDate): int
    {
        $duty = array_fill_keys(array_map(static fn ($date) => substr((string) $date, 0, 10), $dutyDates), true);

        if (!isset($duty[$asOfDate])) {
            return 0;
        }

        $run = 1;

        for ($offset = -1; isset($duty[DateHelper::addDays($asOfDate, $offset)]); $offset--) {
            $run++;
        }

        for ($offset = 1; isset($duty[DateHelper::addDays($asOfDate, $offset)]); $offset++) {
            $run++;
        }

        return $run;
    }

    public static function exceedsLimit(int $run, int $limit): bool
    {
        return $limit > 0 && $run > $limit;
    }

    public static function message(int $run, int $limit): string
    {
        return sprintf('%d consecutive duty days exceeds the %d-day limit.', $run, $limit);
    }
}
