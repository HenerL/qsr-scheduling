<?php

namespace App\Repositories;

use App\Models\Schedule;
use App\Models\User;
use App\Repositories\Interfaces\ScheduleRepositoryInterface;

class ScheduleRepository implements ScheduleRepositoryInterface
{
    public function findOrCreateForWeek(int $storeId, string $weekStartDate): Schedule
    {
        return Schedule::firstOrCreate(
            ['store_id' => $storeId, 'week_start_date' => $weekStartDate],
            ['status' => Schedule::STATUS_DRAFT],
        );
    }

    public function findInStore(int $storeId, int $scheduleId): ?Schedule
    {
        return Schedule::forStore($storeId)->find($scheduleId);
    }

    public function findByWeek(int $storeId, string $weekStartDate): ?Schedule
    {
        return Schedule::forStore($storeId)->where('week_start_date', $weekStartDate)->first();
    }

    public function update(Schedule $schedule, array $data): void
    {
        $schedule->fill($data)->save();
    }

    public function markPublished(Schedule $schedule, User $publisher): void
    {
        $schedule->fill([
            'status' => Schedule::STATUS_PUBLISHED,
            'published_at' => now(),
            'published_by' => $publisher->id,
        ])->save();
    }
}
