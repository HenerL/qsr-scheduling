<?php

namespace App\Mappers\Schedules;

use App\Helpers\DateHelper;
use App\Models\Schedule;

class ScheduleMapper
{
    public static function map(Schedule $schedule): array
    {
        $weekStartDate = $schedule->week_start_date->toDateString();

        return [
            'schedule_id' => $schedule->id,
            'week_start_date' => $weekStartDate,
            'week_end_date' => DateHelper::addDays($weekStartDate, 6),
            'dates' => DateHelper::weekDates($weekStartDate),
            'status' => $schedule->status,
            'is_published' => $schedule->isPublished(),
            'published_at' => $schedule->published_at?->toISOString(),
            'published_by_name' => $schedule->publisher?->name,
            'notes' => $schedule->notes,
        ];
    }

    /** Crew payload when the week has no published schedule — never creates a draft. */
    public static function unpublishedWeek(string $weekStartDate): array
    {
        return [
            'schedule_id' => null,
            'week_start_date' => $weekStartDate,
            'week_end_date' => DateHelper::addDays($weekStartDate, 6),
            'dates' => DateHelper::weekDates($weekStartDate),
            'status' => Schedule::STATUS_DRAFT,
            'is_published' => false,
            'published_at' => null,
            'published_by_name' => null,
            'notes' => null,
        ];
    }
}
