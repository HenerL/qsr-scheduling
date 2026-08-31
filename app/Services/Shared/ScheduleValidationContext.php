<?php

namespace App\Services\Shared;

use App\Models\Employee;
use App\Services\Shared\CoreFunctions\ScheduleTotalsCoreFunction;

/**
 * Everything the schedule rules need for one week, fetched once.
 *
 * Validating a candidate shift on its own would query the store hours, the employee's
 * neighbouring shifts, their duty dates and the station list every time — bulk tagging
 * 7 days × 10 employees would fire hundreds of queries. So the service builds this
 * context once per request and each candidate is checked against it in memory.
 *
 * `remember()` / `forget()` keep it truthful during a bulk batch: row 3 is validated
 * against rows 1 and 2 that were just written, not against the state at request start.
 */
class ScheduleValidationContext
{
    /**
     * @param  array  $daysByDate  date => ScheduleBoardMapper::day() row, the 7 week dates
     * @param  array  $shiftsByEmployee  employee_id => ScheduleShiftMapper rows, week padded 1 day either side
     * @param  array  $dutyDatesByEmployee  employee_id => 'Y-m-d' duty dates, week padded 6 days either side
     * @param  array  $employees  employee_id => Employee
     * @param  array  $stations  ScheduleBoardMapper::station() rows
     */
    public function __construct(
        private readonly array $daysByDate,
        private array $shiftsByEmployee,
        private array $dutyDatesByEmployee,
        private readonly array $employees,
        private readonly array $stations,
        private readonly int $maxConsecutiveDutyDays,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function weekDates(): array
    {
        return array_keys($this->daysByDate);
    }

    /** Closed days cannot be under-staffed, so coverage only looks at these. */
    public function openDates(): array
    {
        return array_keys(array_filter($this->daysByDate, static fn (array $day) => !empty($day['is_open'])));
    }

    public function days(): array
    {
        return array_values($this->daysByDate);
    }

    public function hoursForDate(string $date): array
    {
        return $this->daysByDate[$date] ?? ['is_open' => false, 'is_24_hours' => false, 'open_time' => null, 'close_time' => null];
    }

    public function coversDate(string $date): bool
    {
        return isset($this->daysByDate[$date]);
    }

    /**
     * @return array<int, Employee>
     */
    public function employees(): array
    {
        return $this->employees;
    }

    public function employee(int $employeeId): ?Employee
    {
        return $this->employees[$employeeId] ?? null;
    }

    public function stations(): array
    {
        return $this->stations;
    }

    public function maxConsecutiveDutyDays(): int
    {
        return $this->maxConsecutiveDutyDays;
    }

    /** The employee's shifts either side of the week — the overlap and rest-gap input. */
    public function shiftsFor(int $employeeId): array
    {
        return $this->shiftsByEmployee[$employeeId] ?? [];
    }

    public function dutyDatesFor(int $employeeId): array
    {
        return $this->dutyDatesByEmployee[$employeeId] ?? [];
    }

    /** Every known shift that falls inside the week — the totals and coverage input. */
    public function shiftsInWeek(): array
    {
        $shifts = [];

        foreach ($this->shiftsByEmployee as $employeeShifts) {
            foreach ($employeeShifts as $shift) {
                $shifts[] = $shift;
            }
        }

        return ScheduleTotalsCoreFunction::onlyDates($shifts, $this->weekDates());
    }

    public function remember(array $shift): void
    {
        $employeeId = (int) $shift['employee_id'];
        $this->forget((int) ($shift['id'] ?? 0));

        $this->shiftsByEmployee[$employeeId][] = $shift;

        if (!empty($shift['is_duty'])) {
            $this->dutyDatesByEmployee[$employeeId][] = (string) $shift['shift_date'];
        }
    }

    public function forget(int $shiftId): void
    {
        if ($shiftId === 0) {
            return;
        }

        foreach ($this->shiftsByEmployee as $employeeId => $shifts) {
            $remaining = array_values(array_filter(
                $shifts,
                static fn (array $shift) => (int) ($shift['id'] ?? 0) !== $shiftId,
            ));

            if (count($remaining) === count($shifts)) {
                continue;
            }

            $this->shiftsByEmployee[$employeeId] = $remaining;
            $this->rebuildDutyDates($employeeId, $shifts);
        }
    }

    /**
     * A date stops being duty only when no other duty shift is left on it — a split
     * shift means two rows, and deleting one of them does not free the day.
     */
    private function rebuildDutyDates(int $employeeId, array $removedFrom): void
    {
        $stillDuty = array_column(
            array_filter($this->shiftsByEmployee[$employeeId], static fn (array $shift) => !empty($shift['is_duty'])),
            'shift_date',
        );

        $dropped = array_diff(array_column(
            array_filter($removedFrom, static fn (array $shift) => !empty($shift['is_duty'])),
            'shift_date',
        ), $stillDuty);

        $this->dutyDatesByEmployee[$employeeId] = array_values(
            array_diff($this->dutyDatesFor($employeeId), $dropped),
        );
    }
}
