<?php

namespace App\Services;

use App\Helpers\DateHelper;
use App\Helpers\ScheduleBoardAccessHelper;
use App\Helpers\UserActivityHelper;
use App\Mappers\Schedules\ScheduleBoardMapper;
use App\Mappers\Schedules\ScheduleMapper;
use App\Mappers\Schedules\ScheduleShiftMapper;
use App\Mappers\ShiftTemplates\ShiftTemplateMapper;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\ScheduleShift;
use App\Models\ShiftTemplate;
use App\Models\Store;
use App\Models\User;
use App\Repositories\Interfaces\EmployeeRepositoryInterface;
use App\Repositories\Interfaces\ScheduleRepositoryInterface;
use App\Repositories\Interfaces\ScheduleShiftRepositoryInterface;
use App\Repositories\Interfaces\ShiftTemplateRepositoryInterface;
use App\Services\Shared\CoreFunctions\ScheduleTotalsCoreFunction;
use App\Services\Shared\CoreFunctions\StationCoverageCoreFunction;
use App\Services\Shared\ScheduleValidationContext;
use App\Services\Shared\ScheduleValidationService;
use App\Services\Shared\StoreContextService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Owns the week the board renders: resolving which week a date belongs to, creating the
 * draft on first open, and assembling the one payload both board views read.
 *
 * Deliberately not paginated — one week of one store is bounded by the schema, and the
 * grid needs every row at once. PaginationService is for lists that grow.
 */
class ScheduleService
{
    public function __construct(
        private readonly ScheduleRepositoryInterface $repository,
        private readonly ScheduleShiftRepositoryInterface $shiftRepository,
        private readonly ShiftTemplateRepositoryInterface $templateRepository,
        private readonly EmployeeRepositoryInterface $employeeRepository,
        private readonly ScheduleValidationService $validation,
        private readonly StoreContextService $storeContext,
    ) {
    }

    public function week(User $user, ?string $date): array
    {
        $store = $this->storeContext->requireForUser($user);
        $weekStartDate = $this->resolveWeekStart($store, $date);
        $schedule = $this->repository->findOrCreateForWeek($store->id, $weekStartDate);
        $context = $this->validation->makeContext($store, $weekStartDate);
        $crewOnly = ScheduleBoardAccessHelper::isCrewScoped($user);

        $shiftRows = ScheduleBoardAccessHelper::crewShifts(
            $user,
            $this->shiftRepository->getForSchedule($schedule->id),
        );

        $shifts = $shiftRows
            ->map(static fn (ScheduleShift $shift) => ScheduleShiftMapper::map($shift))
            ->all();

        $templates = $this->templateRepository->getActiveForStore($store->id);
        if ($crewOnly) {
            $templates = $templates->filter(
                static fn (ShiftTemplate $template) => $template->appliesToCrew(),
            )->values();
        }

        return [
            'week' => ScheduleMapper::map($schedule),
            'days' => $context->days(),
            'employees' => $this->employeeRows($context->employees(), $shiftRows, ScheduleTotalsCoreFunction::hoursByEmployee($shifts), $crewOnly),
            'shifts' => $shifts,
            'templates' => $templates
                ->map(static fn (ShiftTemplate $template) => ShiftTemplateMapper::map($template))
                ->all(),
            'warnings' => $this->scopedWarnings($this->validation->weekWarnings($context), $context, $crewOnly),
        ];
    }

    /**
     * @return array{copied: int}
     */
    public function copyFrom(User $user, Schedule $schedule, string $sourceWeekStartDate): array
    {
        ScheduleBoardAccessHelper::assertCanMutate($user, $schedule);

        $store = $this->storeContext->requireForUser($user);
        $source = $this->repository->findByWeek($store->id, $sourceWeekStartDate);
        $crewOnly = ScheduleBoardAccessHelper::isCrewScoped($user);

        if ($source === null) {
            abort(400, 'No schedule found for the given week.');
        }

        $sourceStart = $source->week_start_date->toDateString();
        $targetStart = $schedule->week_start_date->toDateString();
        $offsetDays = DateHelper::daysBetween($sourceStart, $targetStart);

        $toCreate = [];
        $now = now();

        foreach ($this->shiftRepository->getForSchedule($source->id) as $sourceShift) {
            if ($crewOnly && ($sourceShift->employee === null || !$sourceShift->employee->isCrew())) {
                continue;
            }

            $toCreate[] = [
                'store_id' => $store->id,
                'schedule_id' => $schedule->id,
                'employee_id' => $sourceShift->employee_id,
                'shift_date' => DateHelper::addDays($sourceShift->shift_date->toDateString(), $offsetDays),
                'shift_template_id' => $sourceShift->shift_template_id,
                'start_time' => $sourceShift->start_time,
                'end_time' => $sourceShift->end_time,
                'break_minutes' => $sourceShift->break_minutes,
                'crew_station_id' => $sourceShift->crew_station_id,
                'manager_position_id' => $sourceShift->manager_position_id,
                'is_rest_day' => $sourceShift->is_rest_day,
                'remarks' => $sourceShift->remarks,
                'created_by' => $user->id,
                'updated_by' => $user->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $copied = DB::transaction(function () use ($schedule, $toCreate, $crewOnly): int {
            if ($crewOnly) {
                $this->shiftRepository->deleteForScheduleByEmployeeType($schedule->id, Employee::TYPE_CREW);
            } else {
                $this->shiftRepository->deleteForSchedule($schedule->id);
            }

            return $this->shiftRepository->insertMany($toCreate);
        });

        UserActivityHelper::log(
            'schedules',
            'copy_week',
            "Copied {$copied} shift(s) from {$sourceStart} onto {$targetStart}.",
            $schedule->id,
        );

        return ['copied' => $copied];
    }

    /**
     * @return array{schedule: array, warnings: array}
     */
    public function publish(User $user, Schedule $schedule): array
    {
        $store = $this->storeContext->requireForUser($user);
        $weekStartDate = $schedule->week_start_date->toDateString();
        $context = $this->validation->makeContext($store, $weekStartDate);

        $blocks = [];
        foreach ($this->shiftRepository->getForSchedule($schedule->id) as $index => $shift) {
            $candidate = ScheduleShiftMapper::map($shift);
            $blocks = [
                ...$blocks,
                ...$this->validation->blocks($candidate, $context, "shifts.{$index}."),
            ];
        }

        $this->validation->assertNotBlocked($blocks, 'This week cannot be published until the blocking rules are fixed.');

        $this->repository->markPublished($schedule, $user);
        $schedule->refresh();

        UserActivityHelper::log('schedules', 'publish', "Published week {$weekStartDate}.", $schedule->id);

        return [
            'schedule' => ScheduleMapper::map($schedule->load('publisher')),
            'warnings' => $this->validation->weekWarnings($context),
        ];
    }

    /**
     * Monthly roster for the summarize view. Read-only: never creates a draft week.
     */
    public function month(User $user, ?string $yearMonth): array
    {
        $store = $this->storeContext->requireForUser($user);
        $month = DateHelper::monthMeta($yearMonth ?: DateHelper::monthKey(now()->toDateString()));
        $crewOnly = ScheduleBoardAccessHelper::isCrewScoped($user);

        $shiftRows = ScheduleBoardAccessHelper::crewShifts(
            $user,
            $this->shiftRepository->getForStoreDates($store->id, $month['start_date'], $month['end_date']),
        );

        $shifts = $shiftRows
            ->map(static fn (ScheduleShift $shift) => ScheduleShiftMapper::map($shift))
            ->all();

        return [
            'month' => $month,
            'days' => $this->validation->daysForDates($store, DateHelper::monthDates($month['key'])),
            'employees' => $this->employeeRows(
                $this->employeeRepository->getActiveForStore($store->id)->keyBy('id')->all(),
                $shiftRows,
                ScheduleTotalsCoreFunction::hoursByEmployee($shifts),
                $crewOnly,
            ),
            'shifts' => $shifts,
        ];
    }

    public function summary(User $user, Schedule $schedule): array
    {
        $store = $this->storeContext->requireForUser($user);
        $context = $this->validation->makeContext($store, $schedule->week_start_date->toDateString());
        $mapped = ScheduleBoardAccessHelper::crewShifts(
            $user,
            $this->shiftRepository->getForSchedule($schedule->id),
        )
            ->map(static fn (ScheduleShift $shift) => ScheduleShiftMapper::map($shift))
            ->all();

        $hoursByDate = ScheduleTotalsCoreFunction::hoursByDate($mapped);
        $perDayHours = [];
        foreach ($context->weekDates() as $date) {
            $perDayHours[$date] = $hoursByDate[$date] ?? 0.0;
        }

        return [
            'per_day_hours' => $perDayHours,
            'per_employee_hours' => ScheduleTotalsCoreFunction::hoursByEmployee($mapped),
            'coverage_gaps' => StationCoverageCoreFunction::gaps(
                ScheduleTotalsCoreFunction::stationCountsByDate($mapped),
                $context->stations(),
                $context->openDates(),
            ),
        ];
    }

    public function crewMySchedule(User $user, ?string $date): array
    {
        $employee = $this->employeeRepository->findByUserId($user->id);
        if ($employee === null || !$employee->isCrew()) {
            abort(403, 'Crew access required.');
        }

        $store = $this->storeContext->requireForUser($user);
        $weekStartDate = $this->resolveWeekStart($store, $date);
        $schedule = $this->repository->findByWeek($store->id, $weekStartDate);
        $isPublished = $schedule !== null && $schedule->isPublished();

        $shifts = [];
        if ($isPublished && $schedule !== null) {
            $weekDates = DateHelper::weekDates($weekStartDate);
            $shifts = $this->shiftRepository
                ->getForEmployeeDates($store->id, [$employee->id], $weekStartDate, $weekDates[6])
                ->map(static fn (ScheduleShift $shift) => ScheduleShiftMapper::map($shift))
                ->all();
        }

        return [
            'week' => $isPublished && $schedule !== null
                ? ScheduleMapper::map($schedule)
                : ScheduleMapper::unpublishedWeek($weekStartDate),
            'shifts' => $shifts,
        ];
    }

    /**
     * Any date in the week resolves to the same draft, so opening the board on a
     * Wednesday never creates a second schedule for that week.
     */
    public function resolveWeekStart(Store $store, ?string $date): string
    {
        return DateHelper::weekStartDate($date ?: now()->toDateString(), (int) $store->week_starts_on);
    }

    public function requireSchedule(Store $store, int $scheduleId): Schedule
    {
        $schedule = $this->repository->findInStore($store->id, $scheduleId);

        if ($schedule === null) {
            abort(404, 'Schedule not found.');
        }

        return $schedule;
    }

    /**
     * @param  array<int, Employee>  $employees
     * @param  Collection<int, ScheduleShift>  $shiftRows
     * @param  array<int, float>  $hoursByEmployee
     */
    private function employeeRows(
        array $employees,
        Collection $shiftRows,
        array $hoursByEmployee,
        bool $crewOnly,
    ): array {
        // A shift tagged before the employee was deactivated keeps its row, or it would
        // sit in the schedule invisibly with no way for the manager to remove it.
        foreach ($shiftRows as $shift) {
            if ($shift->employee !== null && !isset($employees[(int) $shift->employee->id])) {
                $employees[(int) $shift->employee->id] = $shift->employee;
            }
        }

        if ($crewOnly) {
            $employees = array_filter(
                $employees,
                static fn (Employee $employee) => $employee->isCrew(),
            );
        }

        return array_map(
            static fn (Employee $employee) => ScheduleBoardMapper::employeeRow(
                $employee,
                $hoursByEmployee[(int) $employee->id] ?? 0.0,
            ),
            array_values($employees),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $warnings
     * @return array<int, array<string, mixed>>
     */
    private function scopedWarnings(array $warnings, ScheduleValidationContext $context, bool $crewOnly): array
    {
        if (!$crewOnly) {
            return $warnings;
        }

        $crewIds = [];
        foreach ($context->employees() as $employee) {
            if ($employee->isCrew()) {
                $crewIds[(int) $employee->id] = true;
            }
        }

        return array_values(array_filter(
            $warnings,
            static function (array $warning) use ($crewIds): bool {
                if ($warning['employee_id'] === null) {
                    return true;
                }

                return isset($crewIds[(int) $warning['employee_id']]);
            },
        ));
    }
}
