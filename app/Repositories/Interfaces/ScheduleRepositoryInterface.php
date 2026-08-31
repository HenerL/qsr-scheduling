<?php

namespace App\Repositories\Interfaces;

use App\Models\Schedule;
use App\Models\User;

interface ScheduleRepositoryInterface
{
    public function findOrCreateForWeek(int $storeId, string $weekStartDate): Schedule;

    public function findInStore(int $storeId, int $scheduleId): ?Schedule;

    public function findByWeek(int $storeId, string $weekStartDate): ?Schedule;

    public function update(Schedule $schedule, array $data): void;

    public function markPublished(Schedule $schedule, User $publisher): void;
}
