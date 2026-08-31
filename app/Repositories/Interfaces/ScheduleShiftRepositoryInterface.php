<?php

namespace App\Repositories\Interfaces;

use App\Models\ScheduleShift;
use Illuminate\Database\Eloquent\Collection;

interface ScheduleShiftRepositoryInterface
{
    public function getForSchedule(int $scheduleId): Collection;

    public function findInStore(int $storeId, int $shiftId): ?ScheduleShift;

    /**
     * The employee's shifts in a date range — the input for the overlap, rest-day and
     * rest-gap rules, which all need neighbours outside the edited date.
     */
    public function getForEmployeeDates(int $storeId, array $employeeIds, string $fromDate, string $toDate): Collection;

    /** Every scheduled shift in the store between the two dates, inclusive. */
    public function getForStoreDates(int $storeId, string $fromDate, string $toDate): Collection;

    /**
     * @return array<int, array<int, string>> employee_id => ['2026-08-19', …] duty dates only
     */
    public function getDutyDatesForEmployees(int $storeId, array $employeeIds, string $fromDate, string $toDate): array;

    public function create(array $data): ScheduleShift;

    public function update(ScheduleShift $shift, array $data): void;

    public function delete(ScheduleShift $shift): void;

    public function insertMany(array $rows): int;

    public function deleteForSchedule(int $scheduleId): int;

    public function deleteForScheduleByEmployeeType(int $scheduleId, string $employeeType): int;
}
