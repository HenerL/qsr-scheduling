<?php

namespace App\Helpers;

class TimeHelper
{
    public static function toMinutes(?string $time): int
    {
        if ($time === null || $time === '') {
            return 0;
        }

        [$hour, $minute] = array_pad(explode(':', substr($time, 0, 5)), 2, '0');

        return ((int) $hour * 60) + (int) $minute;
    }

    public static function crossesMidnight(string $start, string $end): bool
    {
        return self::toMinutes($end) <= self::toMinutes($start);
    }

    public static function durationMinutes(string $start, string $end): int
    {
        $endMinutes = self::toMinutes($end);
        if (self::crossesMidnight($start, $end)) {
            $endMinutes += 1440;
        }

        return $endMinutes - self::toMinutes($start);
    }

    public static function netHours(string $start, string $end, int $breakMinutes = 0): float
    {
        $netMinutes = max(0, self::durationMinutes($start, $end) - $breakMinutes);

        return round($netMinutes / 60, 2);
    }

    public static function rangesOverlap(string $aStart, string $aEnd, string $bStart, string $bEnd): bool
    {
        [$aFrom, $aTo] = self::expandedRange($aStart, $aEnd);
        [$bFrom, $bTo] = self::expandedRange($bStart, $bEnd);

        return $aFrom < $bTo && $bFrom < $aTo;
    }

    private static function expandedRange(string $start, string $end): array
    {
        $from = self::toMinutes($start);
        $to = self::toMinutes($end) + (self::crossesMidnight($start, $end) ? 1440 : 0);

        return [$from, $to];
    }
}
