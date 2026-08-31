<?php

namespace App\Helpers;

use Illuminate\Support\Carbon;

class DateHelper
{
    public static function weekStartDate(string $date, int $weekStartsOn = 0): string
    {
        $carbon = Carbon::parse($date)->startOfDay();
        $offset = ($carbon->dayOfWeek - $weekStartsOn + 7) % 7;

        return $carbon->subDays($offset)->toDateString();
    }

    public static function weekDates(string $weekStartDate, int $length = 7): array
    {
        $start = Carbon::parse($weekStartDate)->startOfDay();

        return collect(range(0, $length - 1))
            ->map(static fn (int $offset) => $start->copy()->addDays($offset)->toDateString())
            ->all();
    }

    public static function addDays(string $date, int $days): string
    {
        return Carbon::parse($date)->addDays($days)->toDateString();
    }

    public static function daysBetween(string $fromDate, string $toDate): int
    {
        return (int) Carbon::parse($fromDate)->startOfDay()->diffInDays(Carbon::parse($toDate)->startOfDay(), false);
    }

    /** YYYY-MM for the calendar month that contains $date. */
    public static function monthKey(string $date): string
    {
        return Carbon::parse($date)->format('Y-m');
    }

    public static function monthStartDate(string $yearMonth): string
    {
        return self::monthCarbon($yearMonth)->startOfMonth()->toDateString();
    }

    public static function monthEndDate(string $yearMonth): string
    {
        return self::monthCarbon($yearMonth)->endOfMonth()->toDateString();
    }

    /**
     * @return array<int, string>
     */
    public static function monthDates(string $yearMonth): array
    {
        $start = self::monthCarbon($yearMonth)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $dates = [];

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $dates[] = $day->toDateString();
        }

        return $dates;
    }

    /**
     * @return array{key: string, year: int, month: int, title: string, start_date: string, end_date: string}
     */
    public static function monthMeta(string $yearMonth): array
    {
        $start = self::monthCarbon($yearMonth)->startOfMonth();

        return [
            'key' => $start->format('Y-m'),
            'year' => (int) $start->year,
            'month' => (int) $start->month,
            'title' => $start->format('F'),
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->endOfMonth()->toDateString(),
        ];
    }

    private static function monthCarbon(string $yearMonth): Carbon
    {
        if (preg_match('/^\d{4}-\d{2}$/', $yearMonth) === 1) {
            return Carbon::createFromFormat('Y-m-d', $yearMonth.'-01')->startOfDay();
        }

        return Carbon::parse($yearMonth)->startOfDay();
    }
}
