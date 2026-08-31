<?php

namespace App\Repositories;

use App\Models\ScheduleShift;
use App\Repositories\Interfaces\ScheduleShiftRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ScheduleShiftRepository implements ScheduleShiftRepositoryInterface
{
    // `employee.stations` rides along so the board and the eligibility rule never lazy-load per row.
    private const RELATIONS = ['employee.stations', 'template', 'station', 'position'];

    public function getForSchedule(int $scheduleId): Collection
    {
        return ScheduleShift::with(self::RELATIONS)
            ->where('schedule_id', $scheduleId)
            ->orderBy('shift_date')
            ->orderBy('start_time')
            ->get();
    }

    public function findInStore(int $storeId, int $shiftId): ?ScheduleShift
    {
        return ScheduleShift::forStore($storeId)->with(self::RELATIONS)->find($shiftId);
    }

    public function getForEmployeeDates(int $storeId, array $employeeIds, string $fromDate, string $toDate): Collection
    {
        return ScheduleShift::forStore($storeId)
            ->with(self::RELATIONS)
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('shift_date', [$fromDate, $toDate])
            ->where('status', ScheduleShift::STATUS_SCHEDULED)
            ->orderBy('shift_date')
            ->orderBy('start_time')
            ->get();
    }

    public function getForStoreDates(int $storeId, string $fromDate, string $toDate): Collection
    {
        return ScheduleShift::forStore($storeId)
            ->with(self::RELATIONS)
            ->whereBetween('shift_date', [$fromDate, $toDate])
            ->where('status', ScheduleShift::STATUS_SCHEDULED)
            ->orderBy('shift_date')
            ->orderBy('start_time')
            ->get();
    }

    public function getDutyDatesForEmployees(int $storeId, array $employeeIds, string $fromDate, string $toDate): array
    {
        // A rest day or a cancelled shift is not duty, so it never reaches the run counter.
        return ScheduleShift::forStore($storeId)
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('shift_date', [$fromDate, $toDate])
            ->where('status', ScheduleShift::STATUS_SCHEDULED)
            ->where('is_rest_day', false)
            ->get(['employee_id', 'shift_date'])
            ->groupBy('employee_id')
            ->map(static fn (Collection $shifts) => $shifts
                ->map(static fn (ScheduleShift $shift) => $shift->shift_date->toDateString())
                ->unique()
                ->values()
                ->all())
            ->all();
    }

    public function create(array $data): ScheduleShift
    {
        return ScheduleShift::create($data)->load(self::RELATIONS);
    }

    public function update(ScheduleShift $shift, array $data): void
    {
        $shift->fill($data)->save();
        $shift->load(self::RELATIONS);
    }

    public function delete(ScheduleShift $shift): void
    {
        $shift->delete();
    }

    public function insertMany(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        ScheduleShift::insert($rows);

        return count($rows);
    }

    public function deleteForSchedule(int $scheduleId): int
    {
        return ScheduleShift::where('schedule_id', $scheduleId)->delete();
    }

    public function deleteForScheduleByEmployeeType(int $scheduleId, string $employeeType): int
    {
        return ScheduleShift::where('schedule_id', $scheduleId)
            ->whereHas('employee', static fn ($query) => $query->where('employee_type', $employeeType))
            ->delete();
    }
}
